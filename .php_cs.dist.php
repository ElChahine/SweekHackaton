<?php

return (new PhpCsFixer\Config())
    ->setRules(
        [
            '@PSR1' => true,
            '@PSR2' => true,
            '@Symfony' => true,
            'increment_style' => false,
            'single_line_throw' => false,
            'binary_operator_spaces' => ['operators' => []],
            'doctrine_annotation_array_assignment' => true,
            'doctrine_annotation_braces' => true,
            'doctrine_annotation_indentation' => true,
            'doctrine_annotation_spaces' => true,
            'phpdoc_order' => true,
            'general_phpdoc_annotation_remove' => ['annotations' => ["author", "package"]],
            'align_multiline_comment' => true,
            'combine_consecutive_issets' => true,
            'combine_consecutive_unsets' => true,
            'compact_nullable_type_declaration' => true,
            'linebreak_after_opening_tag' => true,
            'method_chaining_indentation' => true,
            'multiline_comment_opening_closing' => true,
            'multiline_whitespace_before_semicolons' => ['strategy' => 'new_line_for_chained_calls'],
            'no_unused_imports' => true,
            'no_superfluous_elseif' => true,
            'no_useless_else' => true,
            'no_useless_return' => true,
            'phpdoc_add_missing_param_annotation' => true,
            'phpdoc_types_order' => true,
            'nullable_type_declaration_for_default_null_value' => false,
        ]
    )
    ->setCacheFile(__DIR__.'/.php_cs.cache')
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__.'/src'])
            ->notPath('Core/Configuration/DefinitionBuilder.php')
    );
