<?php

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
// use WPGraphQL\Data\Connection\MenuConnectionResolver;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Model\Post as PostModel;

/**
 * Replaces the original handler for the `[modularity]` shortcode so that it
 * works better with the HTML processor in Gatsby.
 */
add_action("init", function () {
  add_shortcode("modularity", function ($atts) {
    $attsstring = "";
    foreach ($atts as $key => $value) {
      $attsstring .= " " . $key . '="' . htmlspecialchars($value) . '"';
    }
    return "<modularity$attsstring></modularity>";
  });
});

/**
 * Adds `contentModularityModules` connection to `NodeWithContentEditor`
 * interface.
 */
add_action("graphql_register_types", function ($type_registry) {
  $type_registry->register_connection([
    "fromType" => "NodeWithContentEditor",
    "toType" => "ContentNode",
    "fromFieldName" => "contentModularityModules",
    "resolve" => function (
      PostModel $parent,
      $args,
      AppContext $context,
      ResolveInfo $info
    ) {
      if (!isset($parent->databaseId)) {
        return null;
      }

      $post = get_post($parent->ID);
      if (empty($post)) {
        return null;
      }
      $content = $post->post_content;

      /**
       * Copied from `do_shortcode`
       */
      // Find all registered tag names in $content.
      preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches);
      $tagnames = array_intersect(["modularity"], $matches[1]);
      if (empty($tagnames)) {
        return null;
      }
      $pattern = get_shortcode_regex($tagnames);
      $module_ids = [];
      preg_match_all("/$pattern/", $content, $matches, PREG_SET_ORDER);

      /**
       * Copied from `do_shortcode_tag`
       */
      foreach ($matches as $m) {
        // Allow [[foo]] syntax for escaping a tag.
        if ("[" === $m[1] && "]" === $m[6]) {
          continue;
        }
        $attr = shortcode_parse_atts($m[3]);
        if (!empty($attr["id"])) {
          $module_ids[] = $attr["id"];
        }
      }

      $resolver = new PostObjectConnectionResolver(
        $parent,
        $args,
        $context,
        $info,
        "any"
      );
      $resolver->set_query_arg("post__in", $module_ids);
      $resolver->set_query_arg("orderby", "post__in");

      return null !== $resolver ? $resolver->get_connection() : null;
    },
  ]);
});
