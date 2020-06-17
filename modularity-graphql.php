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
use WPGraphQL\ACF\Config;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\PostType;

function _ws_kebab_to_camel($string, $capitalizeFirstCharacter = false) {
  $str = str_replace('-', '', ucwords($string, '-'));
  if (!$capitalizeFirstCharacter) {
    $str = lcfirst($str);
  }
  return $str;
}

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
            if (is_string($module['hidden'])) {
              return $module['hidden'] === "true";
            }

            return $module['hidden'];
          },
        ],
      ],
    ]);

    $post_types = ['page', 'post', 'operational-status', 'bulletin-board'];

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

      // Add `postType` field to all post types
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
    $type_registry->register_field("MediaItem", 'fileSize', [
      'type' => 'Int',
      'resolve' => function ($media_item) {
        return filesize(get_attached_file($media_item->ID));
      },
    ]);
    $type_registry->register_field("MediaItem", 'width', [
      'type' => 'Integer',
      'args' => [
        'size' => [
          'type' => 'MediaItemSizeEnum',
          'description' => __(
            'Size of the MediaItem to calculate sizes with',
            'wp-graphql'
          ),
        ],
      ],
      'description' => __(
        'The width attribute value for an image.',
        'wp-graphql'
      ),
      'resolve' => function ($source, $args) {
        $size = 'medium';
        if (!empty($args['size'])) {
          $size = $args['size'];
        }
        $src = wp_get_attachment_image_src($source->ID, $size);
        return $src[1];
      },
    ]);
    $type_registry->register_field("MediaItem", 'height', [
      'type' => 'Integer',
      'args' => [
        'size' => [
          'type' => 'MediaItemSizeEnum',
          'description' => __(
            'Size of the MediaItem to calculate sizes with',
            'wp-graphql'
          ),
        ],
      ],
      'description' => __(
        'The height attribute value for an image.',
        'wp-graphql'
      ),
      'resolve' => function ($source, $args) {
        $size = 'medium';
        if (!empty($args['size'])) {
          $size = $args['size'];
        }
        $src = wp_get_attachment_image_src($source->ID, $size);
        return $src[2];
      },
    ]);

    // Add `posts` field to `ModPosts`
    $type_registry->register_connection([
      'fromType' => 'ModPosts',
      'fromFieldName' => 'posts',
      'toType' => 'PostObjectUnion',
      // 'connectionArgs' => [],
      'resolveNode' => function ($id, $args, $context, $info) {
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
        $field_config['type'] = 'PostType';
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
