<?php
/**
 * Plugin Name: Modularity GraphQL
 * Plugin URI: -
 * Description: Adds Modularity modules to the WPGraphQL Schema
 * Version: 1.0.0
 * Author: Whitespace Dev
 * Author URI: https://www.whitespace.se/
 */

namespace ModularityGraphQL;

use WPGraphQL\AppContext;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Data\DataSource;

function _ws_kebab_to_camel($string, $capitalizeFirstCharacter = false) {
  $str = str_replace('-', '', ucwords($string, '-'));
  if (!$capitalizeFirstCharacter) {
    $str = lcfirst($str);
  }
  return $str;
}

/**
 * TODO: GraphQL types and fields can’t start with digits, so we have to set a
 * new name for the 404 options page if we want to use it.
 */
// add_filter('acf/validate_options_page', function ($page) {
//   switch ($page['menu_slug']) {
//     case 'acf-options-404':
//       $page['page_title'] = 'Error pages';
//       $page['menu_title'] = 'Error pages';
//   }
//   return $page;
// });

add_filter('acf/validate_options_page', function ($page) {
  switch ($page['menu_slug']) {
    case 'acf-options-theme-options':
    case 'acf-options-cstomizer':
    case 'acf-options-navigation':
    case 'acf-options-header':
    case 'acf-options-content':
    case 'acf-options-footer':
    case 'acf-options-search':
    case 'acf-options-archives':
    // case 'acf-options-404':
    case 'acf-options-google-translate':
    case 'acf-options-content-editor':
    case 'acf-options-post-types':
    case 'acf-options-taxonomies':
    case 'acf-options-css':
      $page['show_in_graphql'] = true;
  }
  return $page;
});

add_filter(
  'register_post_type_args',
  function ($args, $post_type) {
    if (preg_match('/^mod-/', $post_type)) {
      $args['show_in_graphql'] = true;
      $args['graphql_single_name'] = _ws_kebab_to_camel($post_type);
      $args['graphql_plural_name'] =
        'all' . _ws_kebab_to_camel($post_type, true);
    }
    return $args;
  },
  10,
  2
);

add_action(
  'graphql_register_types',
  function ($type_registry) {
    global $wp_registered_sidebars;
    global $wp_post_types;

    $modularity_modules_type = [];
    foreach ($wp_registered_sidebars as $sidebar) {
      $modularity_modules_type['fields'][_ws_kebab_to_camel($sidebar['id'])] = [
        'type' => ['list_of' => 'ModularityModuleInstance'],
        'resolve' => function ($meta) use ($sidebar) {
          $modules = [];
          foreach ($meta[$sidebar['id']] ?? [] as $key => $module) {
            $modules[] = ['key' => $key] + $module;
          }
          return $modules;
        },
      ];
    }

    $type_registry->register_object_type(
      'ModularityModules',
      $modularity_modules_type
    );

    $type_registry->register_object_type('ModularityModuleInstance', [
      'fields' => [
        'module' => [
          'type' => 'ModularityModule',
          'resolve' => function (
            $module,
            $args,
            AppContext $context,
            ResolveInfo $info
          ) {
            return DataSource::resolve_post_object($module['postid'], $context);
          },
        ],
        'columnWidth' => [
          'type' => 'String',
          'resolve' => function ($module) {
            return $module['columnWidth'];
          },
        ],
        'hidden' => [
          'type' => 'Boolean',
          'resolve' => function ($module) {
            return $module['hidden'];
          },
        ],
      ],
    ]);

    $post_types = ['page'];

    foreach ($wp_post_types as $post_type_object) {
      if (!in_array($post_type_object->name, $post_types)) {
        continue;
      }

      $type_registry->register_field(
        $post_type_object->graphql_single_name,
        'modularityModules',
        [
          'type' => 'ModularityModules',
          'resolve' => function ($post) {
            $meta = get_post_meta($post->ID, 'modularity-modules', true);
            return $meta ?: [];
          },
        ]
      );
    }

    global $wp_post_types;
    $module_types = [];
    foreach ($wp_post_types as $post_type_slug => $post_type_object) {
      // Only consider modularity module post types
      if (!preg_match('/^mod-/', $post_type_slug)) {
        continue;
      }

      // Populate map of post type slugs and their graphql names
      $module_types[$post_type_slug] = $post_type_object->graphql_single_name;

      // Add `postType` field to all modularity modules
      $type_registry->register_field(
        $post_type_object->graphql_single_name,
        'postType',
        [
          'type' => 'String',
          'resolve' => function ($post) {
            return $post->post_type;
          },
        ]
      );
    }
    $type_registry->register_union_type('ModularityModule', [
      'typeNames' => array_values($module_types),
      'resolveType' => function ($module) use ($module_types, $type_registry) {
        return $type_registry->get_type($module_types[$module->post_type]);
      },
    ]);
  },
  10,
  1
);
