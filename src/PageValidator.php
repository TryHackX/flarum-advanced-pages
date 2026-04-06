<?php

namespace TryHackX\AdvancedPages;

use Flarum\Foundation\AbstractValidator;

class PageValidator extends AbstractValidator
{
    protected array $rules = [
        'title' => ['required', 'string', 'max:200'],
        'slug' => ['required', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        'content' => ['required', 'string'],
        'content_type' => ['required', 'in:html,bbcode,markdown,php,text'],
        'is_published' => ['boolean'],
        'is_hidden' => ['boolean'],
        'is_restricted' => ['boolean'],
        'meta_description' => ['nullable', 'string', 'max:500'],
    ];
}
