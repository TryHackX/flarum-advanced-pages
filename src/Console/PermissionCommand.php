<?php

namespace TryHackX\AdvancedPages\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Group\Group;
use Flarum\Group\Permission;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Manage who may create which type of Advanced Page, from the console.
 *
 * The per-content-type creation permissions — especially `html` (client-side
 * JS) and `php` (server-side code) — are intentionally NOT exposed in the admin
 * permission grid, so they can never be enabled by an accidental click. This
 * command is the deliberate, auditable way to grant them.
 *
 * Examples:
 *   php flarum advancedpages:permission list
 *   php flarum advancedpages:permission grant Members bbcode
 *   php flarum advancedpages:permission grant 4 html --force
 *   php flarum advancedpages:permission revoke "Trusted Authors" php
 */
class PermissionCommand extends AbstractCommand
{
    /**
     * Friendly type aliases → the permission key actually checked at runtime.
     */
    public const TYPE_MAP = [
        'text' => 'advancedPages.create.text',
        'bbcode' => 'advancedPages.create.bbcode',
        'markdown' => 'advancedPages.create.markdown',
        'redirect' => 'advancedPages.create.redirect',
        'html' => 'advancedPages.create.html',
        'php' => 'advancedPages.create.php',
        'manage' => 'advancedPages.manage',
        'spoilers' => 'advancedPages.viewSpoilers',
    ];

    /**
     * Aliases that require --force because they grant code/script execution.
     */
    public const SENSITIVE = ['html', 'php', 'all'];

    protected function configure(): void
    {
        $this
            ->setName('advancedpages:permission')
            ->setDescription('Grant or revoke Advanced Pages creation permissions for a group.')
            ->addArgument('action', InputArgument::REQUIRED, 'grant, revoke, or list')
            ->addArgument('group', InputArgument::OPTIONAL, 'Group id or name (for grant/revoke)')
            ->addArgument('type', InputArgument::OPTIONAL, 'Type: ' . implode(', ', array_keys(self::TYPE_MAP)) . ', or "all" for every create.* type')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Required when granting the sensitive html/php (or all) permissions');
    }

    protected function fire(): int
    {
        $action = strtolower((string) $this->input->getArgument('action'));

        return match ($action) {
            'list' => $this->listPermissions(),
            'grant' => $this->setPermission(true),
            'revoke' => $this->setPermission(false),
            default => $this->fail("Unknown action '$action'. Use: grant, revoke, or list."),
        };
    }

    protected function setPermission(bool $grant): int
    {
        $groupArg = $this->input->getArgument('group');
        $typeArg = strtolower((string) $this->input->getArgument('type'));

        if ($groupArg === null || $typeArg === '') {
            return $this->fail('Both <group> and <type> are required for grant/revoke.');
        }

        $group = $this->resolveGroup((string) $groupArg);
        if (! $group) {
            return $this->fail("No group matches '$groupArg'. Run 'advancedpages:permission list' to see groups.");
        }

        if ($group->id === Group::ADMINISTRATOR_ID) {
            return $this->fail('Administrators already have every permission; nothing to change.');
        }

        // Resolve the alias(es) to concrete permission keys.
        if ($typeArg === 'all') {
            $permissions = [
                self::TYPE_MAP['text'], self::TYPE_MAP['bbcode'], self::TYPE_MAP['markdown'],
                self::TYPE_MAP['html'], self::TYPE_MAP['php'],
            ];
        } elseif (isset(self::TYPE_MAP[$typeArg])) {
            $permissions = [self::TYPE_MAP[$typeArg]];
        } else {
            return $this->fail("Unknown type '$typeArg'. Valid: " . implode(', ', array_keys(self::TYPE_MAP)) . ', all.');
        }

        if ($grant && in_array($typeArg, self::SENSITIVE, true) && ! $this->input->getOption('force')) {
            return $this->fail(
                "Granting '$typeArg' lets this group publish executable " .
                ($typeArg === 'php' ? 'server-side PHP' : 'HTML/JavaScript (and PHP)') .
                " to every visitor. Re-run with --force if you are sure."
            );
        }

        foreach ($permissions as $permission) {
            $exists = Permission::where('group_id', $group->id)
                ->where('permission', $permission)
                ->exists();

            if ($grant && ! $exists) {
                $row = new Permission();
                $row->group_id = $group->id;
                $row->permission = $permission;
                $row->save();
            } elseif (! $grant && $exists) {
                Permission::where('group_id', $group->id)
                    ->where('permission', $permission)
                    ->delete();
            }
        }

        $verb = $grant ? 'Granted' : 'Revoked';
        $prep = $grant ? 'to' : 'from';
        $this->info("$verb [$typeArg] $prep group \"{$group->name_singular}\" (#{$group->id}).");
        $this->info('Changes take effect immediately for new requests.');

        return Command::SUCCESS;
    }

    protected function listPermissions(): int
    {
        $map = Permission::map();
        $groups = Group::orderBy('id')->get()->keyBy('id');

        $this->output->writeln('<info>Advanced Pages permissions</info>');
        $this->output->writeln('');

        foreach (self::TYPE_MAP as $alias => $permission) {
            $groupIds = $map[$permission] ?? [];
            $names = [];
            foreach ($groupIds as $gid) {
                $names[] = isset($groups[(int) $gid])
                    ? $groups[(int) $gid]->name_singular . " (#$gid)"
                    : "#$gid";
            }

            $label = str_pad($alias, 9);
            $holders = $names ? implode(', ', $names) : '—';
            $this->output->writeln("  <comment>$label</comment> $holders");
        }

        $this->output->writeln('');
        $this->output->writeln('Administrators (#1) always have every permission and are not listed.');
        $this->output->writeln('Groups:');
        foreach ($groups as $group) {
            $this->output->writeln("  #{$group->id}  {$group->name_singular}");
        }

        return Command::SUCCESS;
    }

    protected function resolveGroup(string $arg): ?Group
    {
        if (ctype_digit($arg)) {
            return Group::find((int) $arg);
        }

        $needle = mb_strtolower($arg);

        return Group::get()->first(function (Group $group) use ($needle) {
            return mb_strtolower($group->name_singular) === $needle
                || mb_strtolower($group->name_plural) === $needle;
        });
    }

    protected function fail(string $message): int
    {
        $this->error($message);

        return Command::FAILURE;
    }
}
