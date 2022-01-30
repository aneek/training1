<?php
/**
 * @file
 * Lists available colors and color schemes for the IITB DJ theme.
 */

$info = [
  // Available colors and color labels used in theme.
  'fields' => [
    'primary' => t('Primary Color'),
    'secondary' => t('Secondary Color'),
    'text' => t('Text Color'),
    'background' => t('Background Color')
  ],
  // Pre-defined color schemes.
  'schemes' => [
    'default' => [
      'title' => t('Default'),
      'colors' => [
        'primary' => '#3f51b5',
        'secondary' => '#536dfe',
        'text' => '#3b3b3b',
        'background' => '#ffffff'
      ],
    ],
    'red' => [
      'title' => t('Red'),
      'colors' => [
        'primary' => '#f44336',
        'secondary' => '#ffcdd2',
        'text' => '#600000',
        'background' => '#ffffff'
      ]
    ],
    'green' => [
      'title' => t('Green'),
      'colors' => [
        'primary' => '#3fb56c',
        'secondary' => '#53f4fe',
        'text' => '#090707',
        'background' => '#ffffff'
      ]
    ]
  ]
];
// CSS files (excluding @import) to rewrite with new color scheme.
$info['css'] = ['css/color.css'];

// Attach the preview library.
//$info['preview_library'] = 'cake/color.preview';
//$info['preview_html'] = 'color/preview.html';
