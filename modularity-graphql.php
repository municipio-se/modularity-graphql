<?php

/**
 * Plugin Name: Modularity GraphQL
 * Plugin URI: -
 * Description: Adds Modularity modules to the WPGraphQL Schema
 * Version: 5.5.1
 * Author: Whitespace Dev
 * Author URI: https://www.whitespace.se/
 */

namespace ModularityGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use Jawira\CaseConverter\Convert;
use WPGraphQL\ACF\Config;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
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
      $args['show_in_rest'] = true;
      $args['publicly_queryable'] = true;
      $args['public'] = true;
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

            // Modules are not registered on revisions. Look on the original
            if (
              $post->isRevision &&
              isset($post->parentDatabaseId) &&
              absint($post->parentDatabaseId)
            ) {
              $post_id = $post->parentDatabaseId;
            } else {
              $post_id = $post->ID;
            }

            $meta = get_post_meta($post_id, 'modularity-modules', true) ?: [];
            $modules = [];
            foreach ($meta[$sidebar['id']] ?? [] as $key => $module) {
              $modules[] = ['key' => $key, 'area' => $area] + $module;
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
          'type' => 'ContentNode',
          'resolve' => function (
            $module,
            $args,
            AppContext $context,
            ResolveInfo $info
          ) {
            if (!empty($module['postid'])) {
              return $context
                ->get_loader('post')
                ->load_deferred((int) $module['postid'])
                ->then(function ($post) use ($module) {
                  if ($post instanceof Post) {
                    $post->modularity_module = $module;
                  }
                  return $post;
                });
            }
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
              return $module['hidden'] === 'true';
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
    foreach ($wp_post_types as $post_type_slug => $post_type_object) {
      // Only consider modularity module post types
      if (!preg_match('/^mod-/', $post_type_slug)) {
        continue;
      }

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

    // Add `posts` field to `ModPosts`
    $type_registry->register_connection([
      'fromType' => 'ModPosts',
      'fromFieldName' => 'contentNodes',
      'toType' => 'ContentNode',
      // 'connectionArgs' => [],
      'resolve' => function ($root, $args, $context, $info) {
        // TODO: Use `posts_count` field as limit in wp query
        $data_source = get_field('posts_data_source', $root->ID, false);
        $parent_post = $root->modularity_module["area"]["post"] ?? $root;
        switch ($data_source) {
          case 'manual':
            $resolver = new PostObjectConnectionResolver(
              $root,
              $args,
              $context,
              $info,
              'any'
            );
            $data_posts = get_field('posts_data_posts', $root->ID, false);
            $resolver->set_query_arg('post__in', $data_posts);
            $resolver->set_query_arg('orderby', 'post__in');
            break;
          case 'children':
            $data_child_of = get_field('posts_data_child_of', $root->ID, false);
            if (!empty($data_child_of)) {
              $parent_post_object = get_post($data_child_of);
              if (!empty($parent_post_object)) {
                $parent_post = new Post($parent_post_object);
              }
            }
            $post_type = $parent_post->post_type;
            $resolver = new PostObjectConnectionResolver(
              $root,
              $args,
              $context,
              $info,
              $post_type
            );
            $resolver->set_query_arg('post_parent', $parent_post->ID);
            break;
          case 'posttype':
            $post_type = get_field('posts_data_post_type', $root->ID, false);
            $resolver = new PostObjectConnectionResolver(
              $root,
              $args,
              $context,
              $info,
              $post_type
            );
            $resolver->set_query_arg("post__not_in", [$parent_post->ID]);
            break;
          default:
            $resolver = null;
            return null;
        }

        $resolver =
          apply_filters(
            'modularity_graphql/ModPosts/contentNodes/PostObjectConnectionResolver',
            $resolver,
            $data_source,
            $parent_post,
            $root,
            $args,
            $context,
            $info
          ) ?? $resolver;

        $connection = $resolver ? $resolver->get_connection() : null;
        return $connection;
      },
    ]);
  },
  10,
  1
);

// Add `hideTitle` field to `ContentNode`
add_filter(
  'graphql_interface_fields',
  function ($fields, $type_name) {
    if ($type_name == 'ContentNode') {
      $fields['hideTitle'] = [
        'type' => 'Boolean',
        'resolve' => function ($post) {
          $meta = get_post_meta(
            $post->ID,
            'modularity-module-hide-title',
            true
          );
          return $meta ?: false;
        },
      ];
    }
    return $fields;
  },
  10,
  2
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
      'ModPosts_Modpostsdatasource_data',
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
          return array_filter(
            array_map(function ($id) {
              $post = get_post($id);
              return !empty($post) ? new Post($post) : null;
            }, array_unique($matches[1]))
          );
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

define('MODULARITY_GRAPHQL_PATH', dirname(__FILE__));
define(
  'MODULARITY_GRAPHQL_AUTOLOAD_PATH',
  MODULARITY_GRAPHQL_PATH . '/autoload'
);

array_map(static function () {
  include_once func_get_args()[0];
}, glob(MODULARITY_GRAPHQL_AUTOLOAD_PATH . '/*.php'));
