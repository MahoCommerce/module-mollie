<?php

$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(true)
    ->setParallelConfig(new PhpCsFixer\Runner\Parallel\ParallelConfig())
    ->setRules([
        '@PER-CS2.0' => true,
        'logical_operators' => true,
        'modernize_types_casting' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'single_quote' => true,
        'phpdoc_indent' => true,
        'phpdoc_param_order' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_trim' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__ . '/app'])
            ->name(['*.php'])
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
    );
