<?php

namespace ModularityGraphQL;

function set_acf_group_graphql_field_name($key, $name) {
  add_filter('acf/init', function () use ($key, $name) {
    $store = acf_get_local_store('groups');
    $group = $store->get($key);
    if (empty($group)) {
      return;
    }
    $group['show_in_graphql'] = true;
    $group['graphql_field_name'] = $name;
    $store->set($key, $group);
  });
}

// Text
set_acf_group_graphql_field_name('group_5891b49127038', 'modTextOptions');

// Notice
set_acf_group_graphql_field_name('group_575a842dd1283', 'modNoticeOptions');

// Image
set_acf_group_graphql_field_name('group_570770ab8f064', 'modImageOptions');

// Fileslist
set_acf_group_graphql_field_name('group_5756ce3e48783', 'modFileslistOptions');

// Posts
set_acf_group_graphql_field_name('group_571dfd3c07a77', 'modPostsDataDisplay');
set_acf_group_graphql_field_name(
  'group_571e045dd555d',
  'modPostsDataFiltering'
);
set_acf_group_graphql_field_name('group_571dffc63090c', 'modPostsDataSorting');
set_acf_group_graphql_field_name('group_571dfaabc3fc5', 'modPostsDataSource');
