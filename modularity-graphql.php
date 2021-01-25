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

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use Jawira\CaseConverter\Convert;
use WPGraphQL\ACF\Config;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\PostType;

add_filter('acf/load_field_group', function ($field_group) {
  if (
    preg_match('/^\d/', $field_group['title']) &&
    empty($field_group['graphql_field_name'])
  ) {
    $field_group['graphql_field_name'] = Config::camel_case(
      'group' . $field_group['title']
    );
  }
  return $field_group;
});

add_filter('acf/load_field', function ($field) {
  if (
    preg_match('/^\d/', $field['name']) &&
    empty($field['graphql_field_name'])
  ) {
    $field['graphql_field_name'] = Config::camel_case('field' . $field['name']);
  }
  return $field;
});

add_filter('acf/get_options_page', function ($options_page) {
  if (
    preg_match('/^\d/', $options_page['page_title']) &&
    !empty($options_page['show_in_graphql'])
  ) {
    $options_page['page_title'] = Config::camel_case(
      'Options for ' . $options_page['page_title']
    );
  }
  return $options_page;
});

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
    case 'acf-options-404':
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
      $args['graphql_single_name'] = (new Convert($post_type))->toCamel();
      $args['graphql_plural_name'] =
        'all' . (new Convert($post_type))->toPascal();
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

    $type_registry->register_interface_type('NodeWithModularity', [
      'fields' => [
        'modularityAreas' => [
          'type' => ['list_of' => 'NodeModularityArea'],
          'resolve' => function ($post) {
            global $wp_registered_sidebars;
            $areas = [];
            foreach ($wp_registered_sidebars as $sidebar) {
              $areas[] = [
                'sidebar' => $sidebar,
                'post' => $post,
              ];
            }
            return $areas;
          },
        ],
        'modularityArea' => [
          'type' => 'NodeModularityArea',
          'args' => [
            'area' => [
              'type' => 'ModularityAreaEnum',
            ],
          ],
          'resolve' => function ($post, $args) {
            global $wp_registered_sidebars;
            return [
              'sidebar' => $wp_registered_sidebars[$args['area']],
              'post' => $post,
            ];
          },
        ],
      ],
    ]);

    $type_registry->register_object_type('NodeModularityArea', [
      'fields' => [
        'name' => [
          'type' => 'String',
          'resolve' => function ($area) {
            return $area['sidebar']['id'];
          },
        ],
        'modules' => [
          'type' => ['list_of' => 'ModularityModuleInstance'],
          'resolve' => function ($area) {
            $post = $area['post'];
            $sidebar = $area['sidebar'];
            $meta = get_post_meta($post->ID, 'modularity-modules', true) ?: [];
            $modules = [];
            foreach ($meta[$sidebar['id']] ?? [] as $key => $module) {
              $modules[] = ['key' => $key] + $module;
            }
            return $modules;
          },
        ],
      ],
    ]);

    $modularity_area_enums = [];
    foreach ($wp_registered_sidebars as $sidebar) {
      $enum = (new Convert($sidebar['id']))->toMacro();
      $modularity_area_enums[$enum] = $sidebar['id'];
    }

    $type_registry->register_enum_type('ModularityAreaEnum', [
      'values' => $modularity_area_enums,
    ]);

    $type_registry->register_object_type('ModularityModuleInstance', [
      'fields' => [
        'node' => [
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
            if (is_string($module['hidden'])) {
              return $module['hidden'] === "true";
            }

            return $module['hidden'];
          },
        ],
        'key' => [
          'type' => 'String',
          'resolve' => function ($module) {
            return $module['key'];
          },
        ],
      ],
    ]);

    global $wp_post_types;
    $module_types = [];
    foreach ($wp_post_types as $post_type_slug => $post_type_object) {
      // Only consider modularity module post types
      if (!preg_match('/^mod-/', $post_type_slug)) {
        continue;
      }

      // Populate map of post type slugs and their graphql names
      $module_types[$post_type_slug] = $post_type_object->graphql_single_name;

      // Add `hideTitle` field to all modularity modules
      $type_registry->register_field(
        $post_type_object->graphql_single_name,
        'hideTitle',
        [
          'type' => 'Boolean',
          'resolve' => function ($post) {
            $meta = get_post_meta(
              $post->ID,
              'modularity-module-hide-title',
              true
            );
            return $meta ?: false;
          },
        ]
      );

      // Add `nonce` field to modularity form modules
      if ($post_type_slug === 'mod-form') {
        $type_registry->register_field(
          $post_type_object->graphql_single_name,
          'nonce',
          [
            'type' => 'String',
            'resolve' => function ($post) {
              return wp_create_nonce('mod-form-' . $post->ID);
            },
          ]
        );
      }
    }
    $type_registry->register_union_type('ModularityModule', [
      'typeNames' => array_values($module_types),
      'resolveType' => function ($module) use ($module_types, $type_registry) {
        return $type_registry->get_type($module_types[$module->post_type]);
      },
    ]);

    // Add `posts` field to `ModPosts`
    $type_registry->register_connection([
      'fromType' => 'ModPosts',
      'fromFieldName' => 'posts',
      'toType' => 'PostObjectUnion',
      // 'connectionArgs' => [],
      'resolveNode' => function ($id, $args, $context, $info) {
        if($id instanceof Post) {
          $id = $id->ID;
        }
        return DataSource::resolve_post_object($id, $context);
      },
      'resolve' => function ($root, $args, $context, $info) {
        // TODO: Use `posts_count` field as limit in wp query
        $data_source = get_field('posts_data_source', $root->ID, false);
        switch ($data_source) {
          case 'manual':
            $data_posts = get_field('posts_data_posts', $root->ID, false);
            $connection = Relay::connectionFromArray($data_posts, $args);
            $nodes = [];
            if (
              !empty($connection['edges']) &&
              is_array($connection['edges'])
            ) {
              foreach ($connection['edges'] as $edge) {
                $nodes[] = !empty($edge['node']) ? $edge['node'] : null;
              }
            }
            $connection['nodes'] = !empty($nodes) ? $nodes : null;
            $connection = apply_filters(
              'modularity-graphql/ModPosts/posts/connection',
              $connection,
              $data_source,
              $root,
              $args,
              $context,
              $info
            );
            $connection = apply_filters(
              "modularity-graphql/ModPosts/posts/connection/manual",
              $connection,
              $root,
              $args,
              $context,
              $info
            );
            return $connection;
            break;
          case 'children':
            $data_child_of = get_field('posts_data_child_of', $root->ID, false);
            $parent = new Post(get_post($data_child_of));
            $post_types = \WPGraphQL::get_allowed_post_types();
            $connection = DataSource::resolve_post_objects_connection(
              $parent,
              $args,
              $context,
              $info,
              $post_types
            );
            $connection = apply_filters(
              'modularity-graphql/ModPosts/posts/connection',
              $connection,
              $data_source,
              $root,
              $args,
              $context,
              $info
            );
            $connection = apply_filters(
              "modularity-graphql/ModPosts/posts/connection/children",
              $connection,
              $root,
              $args,
              $context,
              $info
            );
            return $connection;
            break;
          case 'posttype':
            $post_type = get_field('posts_data_post_type', $root->ID, false);
            $connection = DataSource::resolve_post_objects_connection(
              null,
              $args,
              $context,
              $info,
              $post_type
            );
            $connection = apply_filters(
              'modularity-graphql/ModPosts/posts/connection',
              $connection,
              $data_source,
              $root,
              $args,
              $context,
              $info
            );
            $connection = apply_filters(
              "modularity-graphql/ModPosts/posts/connection/posttype",
              $connection,
              $post_type,
              $root,
              $args,
              $context,
              $info
            );
            $connection = apply_filters(
              "modularity-graphql/ModPosts/posts/connection/posttype/$post_type",
              $connection,
              $root,
              $args,
              $context,
              $info
            );
            return $connection;
            break;
        }
        return null;
      },
    ]);
  },
  10,
  1
);

add_filter(
  'graphql_object_type_interfaces',
  function ($interfaces, $config, $object_type) {
    global $wp_post_types;
    if (!in_array('ContentNode', $interfaces)) {
      return $interfaces;
    }
    foreach ($wp_post_types as $post_type_name => $post_type_object) {
      if (
        !empty($post_type_object->graphql_single_name) &&
        ucfirst($post_type_object->graphql_single_name) === $config['name']
      ) {
        $modularity_options = get_option('modularity-options');
        $post_types = $modularity_options['enabled-post-types'];
        if (in_array($post_type_name, $post_types)) {
          $interfaces[] = 'NodeWithModularity';
        }
        break;
      }
    }
    return $interfaces;
  },
  10,
  3
);

add_action(
  'graphql_register_types',
  function ($type_registry) {
    $type_registry->register_field(
      'ModPosts_Datasource_data',
      'postContentMedia',
      [
        'type' => ['list_of' => 'MediaItem'],
        'resolve' => function ($source) {
          $post_content = $source['field_57625914110b2'];
          $post_content = apply_filters('the_content', $post_content);
          preg_match_all(
            '/wp-(?:image|caption)-(\d+)/',
            $post_content,
            $matches
          );
          $posts = array_map(function ($id) {
            $post = get_post($id);
            return new Post($post);
          }, array_unique($matches[1]));
          return $posts;
        },
      ]
    );
  },
  10,
  1
);

/**
 * Adds support for dynamic_table ACF fields
 */
add_filter(
  'wpgraphql_acf_supported_fields',
  function ($supported_fields) {
    $supported_fields['dynamic_table'];
    return $supported_fields;
  },
  10,
  1
);

/**
 * Adds support for dynamic_table ACF fields
 */
add_filter(
  'wpgraphql_acf_register_graphql_field',
  function ($field_config, $type_name, $field_name, $config) {
    $acf_field = isset($config['acf_field']) ? $config['acf_field'] : null;
    $acf_type = isset($acf_field['type']) ? $acf_field['type'] : null;

    switch ($acf_type) {
      case 'dynamic_table':
        $field_config['type'] = 'String';
        $field_config['resolve'] = function (
          $root,
          $args,
          $context,
          $info
        ) use ($acf_field) {
          $field_value = get_field($acf_field['key'], $root->ID, false);
          return $field_value;
        };
        break;
      case 'posttype_select':
        $field_config['type'] = 'ContentType';
        $field_config['resolve'] = function (
          $root,
          $args,
          $context,
          $info
        ) use ($acf_field) {
          $field_value = get_field($acf_field['key'], $root->ID, false);
          if (empty($field_value)) {
            return null;
          }
          return new PostType(get_post_type_object($field_value));
        };
        break;
    }

    return $field_config;
  },
  10,
  4
);

add_action(
  'graphql_register_types',
  function ($type_registry) {
    $type_registry->register_field('ContentType', 'modularityEnabled', [
      'type' => 'Boolean',
      'resolve' => function ($post_type) {
        $modularity_options = get_option('modularity-options');
        $post_types = $modularity_options['enabled-post-types'];
        return $post_type->name && in_array($post_type->name, $post_types);
      },
    ]);
  },
  10,
  1
);

add_action(
  'graphql_register_types',
  function ($type_registry) {
    $type_registry->register_object_type('ModularityOptions', [
      'fields' => [
        'enabledModules' => [
          'type' => ['list_of' => 'ContentType'],
          'resolve' => function ($options) {
            return array_map(function ($post_type) {
              return new PostType(get_post_type_object($post_type));
            }, $options['enabled-modules']);
          },
        ],
      ],
    ]);
    $type_registry->register_field('RootQuery', 'modularityOptions', [
      'type' => 'ModularityOptions',
      'resolve' => function () {
        return get_option('modularity-options');
      },
    ]);
  },
  10,
  1
);
