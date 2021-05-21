## 3.3.0 (Maj 21, 2021)

### New features

– Add showInMenu field

## 3.2.0 (April 17, 2021)

### New features

- Allow public querying of module post types

## 3.1.0 (March 19, 2021)

### Bugfixes

- Modules were not registered on revisions

## 3.0.1 (January 25, 2021)

### Bugfixes

- Querying `posts` on `ModPosts` caused an error.

## 3.0.0 (December 15, 2020)

**💥 BREAKING CHANGES:** Restructure schema and remove code that doesn’t belong
in this plugin.

- Register new interface `NodeWithModularity` and add it to all post types with
  Modularity enabled.
- Remove the `modularityModules` field
- Add `modularityArea` and `modularityAreas` fields
- Remove fields on `MediaItem` that should not be here

## 2.1.0 (November 11, 2020)

- Add `modularityModules` to enabled post types instead of hard-coded list of
  post types
- Add `modularityEnabled` field on `ContentType` nodes
- Add `modularityOptions` root-query field

## 2.0.0 (November 6, 2020)

- Fix compatibility with wp-graphql 0.14.x
- BREAKING: Remove fields added by wp-graphql 0.14.x
