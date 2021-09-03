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

// Contacts
set_acf_group_graphql_field_name('group_5805e5dc0a3be', 'modContactsOptions');

// Fileslist
set_acf_group_graphql_field_name('group_5756ce3e48783', 'modFileslistOptions');

// Form
set_acf_group_graphql_field_name('group_58eb301ecb36a', 'modFormOptions');

// Gallery
set_acf_group_graphql_field_name('group_5666af6d26b7c', 'modGalleryOptions');

// Iframe
set_acf_group_graphql_field_name('group_56c47016ea9d5', 'modIframeOptions');

// Image
set_acf_group_graphql_field_name('group_570770ab8f064', 'modImageOptions');

// Notice
set_acf_group_graphql_field_name('group_575a842dd1283', 'modNoticeOptions');

// Posts
set_acf_group_graphql_field_name('group_571dfd3c07a77', 'modPostsDataDisplay');
set_acf_group_graphql_field_name(
  'group_571e045dd555d',
  'modPostsDataFiltering'
);
set_acf_group_graphql_field_name('group_571dffc63090c', 'modPostsDataSorting');
set_acf_group_graphql_field_name('group_571dfaabc3fc5', 'modPostsDataSource');

// RSS
set_acf_group_graphql_field_name('group_59535d940706c', 'modRssOptions');

// Table
set_acf_group_graphql_field_name('group_5666a2a71d806', 'modTableOptions');

// Text
set_acf_group_graphql_field_name('group_5891b49127038', 'modTextOptions');

// Video
set_acf_group_graphql_field_name('group_57454ae7b0e9a', 'modVideoOptions');
