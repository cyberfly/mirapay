<?php

use App\Support\HtmlSanitizer;

it('keeps allowed formatting', function () {
    expect(HtmlSanitizer::clean('<h2>Tajuk</h2><ul><li>a</li></ul><p><em>x</em></p>'))
        ->toBe('<h2>Tajuk</h2><ul><li>a</li></ul><p><em>x</em></p>');
});

it('removes dangerous content', function (string $input) {
    $out = HtmlSanitizer::clean($input);

    expect($out)->not->toContain('<script')->not->toContain('onerror')->not->toContain('javascript:')->not->toContain('<img');
})->with([
    '<script>alert(1)</script><p>ok</p>',
    '<img src=x onerror=alert(1)>',
    '<a href="javascript:alert(1)">x</a>',
    '<p style="x" onclick="y">t</p><style>p{}</style>',
]);

it('adds rel to links and returns empty for blank input', function () {
    expect(HtmlSanitizer::clean('<a href="https://a.test">x</a>'))->toContain('rel="noopener noreferrer nofollow"');
    expect(HtmlSanitizer::clean(null))->toBe('')->and(HtmlSanitizer::clean('  '))->toBe('');
});
