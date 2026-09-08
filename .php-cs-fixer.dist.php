<?php

$headerTemplate = <<<HEADER
This file is part of the "celum_connect_fal" Extension for TYPO3 CMS.

For the full copyright and license information, please read the
LICENSE file that was distributed with this source code.
HEADER;

$config = new PhpCsFixer\Config();
$config->setRiskyAllowed(true);
$config->getFinder()->in(__DIR__);
$config->getFinder()->exclude(['vendor', 'var']);
// ext_emconf.php must not declare strict_types, otherwise the TER upload
// fails. Excluded here so declare_strict_types does not re-add it.
$config->getFinder()->notName('ext_emconf.php');
$config->setRules([
    '@PSR12' => true,
    'array_syntax' => ['syntax' => 'short'],
    'blank_line_after_opening_tag' => true,
    'cast_spaces' => ['space' => 'none'],
    'concat_space' => ['spacing' => 'one'],
    'declare_equal_normalize' => ['space' => 'none'],
    'declare_strict_types' => true,
    'header_comment' => [
        'header' => $headerTemplate,
        'comment_type' => 'comment',
        'location' => 'after_declare_strict',
        'separate' => 'both',
    ],
    'no_empty_phpdoc' => true,
    'no_leading_import_slash' => true,
    'no_unused_imports' => true,
    'no_useless_else' => true,
    'ordered_imports' => true,
    'return_type_declaration' => ['space_before' => 'none'],
    'single_quote' => true,
    'whitespace_after_comma_in_array' => true,
]);

return $config;
