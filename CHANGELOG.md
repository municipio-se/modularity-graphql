## [5.3.0](https://github.com/municipio-se/modularity-graphql/compare/5.2.0...5.3.0) (2022-04-13)


### Features

* Pass more args to `modularity_graphql/ModPosts/contentNodes/PostObjectConnectionResolver` filter ([6547634](https://github.com/municipio-se/modularity-graphql/commit/65476342fa5f45de1cea638d927f97b0323a9a57))


### Bug Fixes

* Exclude current post from post module ([c6d9a51](https://github.com/municipio-se/modularity-graphql/commit/c6d9a513d824721b4765833bcd8da415f9f6f057))

## [5.2.0](https://github.com/municipio-se/modularity-graphql/compare/5.0.0...5.2.0) (2022-02-21)


### ⚠ BREAKING CHANGES

* Removes `modularity-graphql/ModPosts/posts/connection` filters and uses `WPGraphQL\Data\Connection\PostObjectConnectionResolver`

### Features

* Add `modularity_graphql/ModPosts/contentNodes/PostObjectConnectionResolver` filter ([18cc107](https://github.com/municipio-se/modularity-graphql/commit/18cc10733c0e1d48db123407ce306de7c51ef00d))
* expose form module options in graphql ([d54f113](https://github.com/municipio-se/modularity-graphql/commit/d54f1131045f0dcafebc69eac614112ada6637cf))


### Bug Fixes

* error on missing modules ([709e759](https://github.com/municipio-se/modularity-graphql/commit/709e7592848a1499aa8ed434ed9209533997459a))
* errors in post object connection ([e8748ee](https://github.com/municipio-se/modularity-graphql/commit/e8748eed435825a3f167a4b133bfd946988b771f))
* saves posts in order chosen by the editor ([754f74a](https://github.com/municipio-se/modularity-graphql/commit/754f74a68e254bf8780bc123d046fa79945a96aa))
* When we cant find some postContentMedia dont fail. Just carry on. ([47158c8](https://github.com/municipio-se/modularity-graphql/commit/47158c84c618b03ab85eba593bdd52d48edae48d))

### [4.0.1](https://github.com/municipio-se/modularity-graphql/compare/4.0.0...4.0.1) (2021-08-10)


### Bug Fixes

* error on manual posts ([f87bdc9](https://github.com/municipio-se/modularity-graphql/commit/f87bdc9bde12b3ce1f2d20ee4d0a76cd377c7166))

## [4.0.0](https://github.com/municipio-se/modularity-graphql/compare/3.2.0...4.0.0) (2021-06-16)


### ⚠ BREAKING CHANGES

* move `hideTitle` field from each module type to `ContentNode`
* rename field `posts` to `contentNodes` on `ModPosts`
* replace `ModularityModule` union type with `ContentNode` interface

### Features

* move `hideTitle` field from each module type to `ContentNode` ([aa06371](https://github.com/municipio-se/modularity-graphql/commit/aa06371daceee37d500aba66dc47e59d4c9681f9))
* rename field `posts` to `contentNodes` on `ModPosts` ([67fddf8](https://github.com/municipio-se/modularity-graphql/commit/67fddf84b022e11fd15ff9702334da2f584f5183))
* replace `ModularityModule` union type with `ContentNode` interface ([bfb9dd1](https://github.com/municipio-se/modularity-graphql/commit/bfb9dd18988c17d61aa909f502fb173421d3726e))
* set explicit names for acf field groups ([92d371b](https://github.com/municipio-se/modularity-graphql/commit/92d371b5684fe68960ebf57c3370c57407ae051a))
* set explicit names for acf field groups ([9165148](https://github.com/municipio-se/modularity-graphql/commit/916514829d7773f05644213969c9b58d5e4ed30c))


### Bug Fixes

* avoid errors when parent post isn’t found in Posts module ([5d66048](https://github.com/municipio-se/modularity-graphql/commit/5d66048b7b19f01eea9ba9b1001730fb8ef78dce))
* field group name for text module ([7f2115e](https://github.com/municipio-se/modularity-graphql/commit/7f2115e58ed93363cbead40b6497cc53c83eb9a7))
* posts module did not filter by chosen post type ([629b846](https://github.com/municipio-se/modularity-graphql/commit/629b846e8782998edb1911cdb7dde9cf3ed337df))
* use correct type for `postContentMedia` field ([9493c7e](https://github.com/municipio-se/modularity-graphql/commit/9493c7ed67bb9dcfff2d9d477d8bf3120763fbc3))
* wrong way to add `hideTitle` to `ContentNode` interface ([1abf459](https://github.com/municipio-se/modularity-graphql/commit/1abf45955ff68678b35e4ec10920981dba9b9c8a))

## [3.2.0](https://github.com/municipio-se/modularity-graphql/compare/3.1.0...3.2.0) (2021-04-17)


### Features

* allow public querying of module post types ([720cbee](https://github.com/municipio-se/modularity-graphql/commit/720cbee9c2496936ce9bd1c30249684c5bd8df49))

## [3.1.0](https://github.com/municipio-se/modularity-graphql/compare/3.0.1...3.1.0) (2021-03-19)


### Bug Fixes

* modules were not registered on revisions ([33bbd29](https://github.com/municipio-se/modularity-graphql/commit/33bbd2934e040191dd245ba32a104647df1af744))

### [3.0.1](https://github.com/municipio-se/modularity-graphql/compare/3.0.0...3.0.1) (2021-01-25)


### Bug Fixes

* querying `posts` on `ModPosts` caused an error ([6c5e194](https://github.com/municipio-se/modularity-graphql/commit/6c5e19462fa7aeaee10914031eaec422a6dd99f8))

## [3.0.0](https://github.com/municipio-se/modularity-graphql/compare/2.1.0...3.0.0) (2020-12-15)


### ⚠ BREAKING CHANGES

* remove fields on `MediaItem` that should not be here
* restructure schema

### Code Refactoring

* restructure schema ([1b84386](https://github.com/municipio-se/modularity-graphql/commit/1b84386a1d197ad7d64d95832ee3b901be8fb69b))


### Miscellaneous Chores

* remove fields on `MediaItem` that should not be here ([75a1589](https://github.com/municipio-se/modularity-graphql/commit/75a1589e1bf89ef0e22dfec7313fd99666fe7eaf))

## [2.1.0](https://github.com/municipio-se/modularity-graphql/compare/1.17.0...2.1.0) (2020-11-11)


### ⚠ BREAKING CHANGES

* remove fields added by wp-graphql@0.14x

### Features

* add modularityEnabled on ContentType ([99ddac3](https://github.com/municipio-se/modularity-graphql/commit/99ddac3fdf1f3012bbd12269cdec31abf59d1939))
* add modularityModules to enabled post types ([203f571](https://github.com/municipio-se/modularity-graphql/commit/203f5711f220a1fca73ffd5dacc8d935aee1c057))
* add modularityOptions on RootQuery ([62442e0](https://github.com/municipio-se/modularity-graphql/commit/62442e0c65d7df74c721bc9252b6666ec27d22f4))


### Bug Fixes

* remove fields added by wp-graphql@0.14x ([f33cec5](https://github.com/municipio-se/modularity-graphql/commit/f33cec58305401fe69242fc471f520fdc4330c55))

## [1.17.0](https://github.com/municipio-se/modularity-graphql/compare/1.16.0...1.17.0) (2020-11-05)

## [1.16.0](https://github.com/municipio-se/modularity-graphql/compare/1.15.0...1.16.0) (2020-06-17)

## [1.15.0](https://github.com/municipio-se/modularity-graphql/compare/1.14.0...1.15.0) (2020-06-04)

## [1.14.0](https://github.com/municipio-se/modularity-graphql/compare/1.13.1...1.14.0) (2020-05-26)

### [1.13.1](https://github.com/municipio-se/modularity-graphql/compare/1.12.1...1.13.1) (2020-05-22)

### [1.12.1](https://github.com/municipio-se/modularity-graphql/compare/1.11.1...1.12.1) (2020-05-19)

### [1.11.1](https://github.com/municipio-se/modularity-graphql/compare/1.11.0...1.11.1) (2020-05-05)

## [1.11.0](https://github.com/municipio-se/modularity-graphql/compare/2.0.0...1.11.0) (2020-05-05)

## [1.8.0](https://github.com/municipio-se/modularity-graphql/compare/1.6.0...1.8.0) (2020-03-12)

## [1.6.0](https://github.com/municipio-se/modularity-graphql/compare/1.5.0...1.6.0) (2020-03-11)

## [1.5.0](https://github.com/municipio-se/modularity-graphql/compare/1.4.0...1.5.0) (2020-03-11)

## [1.4.0](https://github.com/municipio-se/modularity-graphql/compare/1.3.0...1.4.0) (2020-03-09)

## [1.3.0](https://github.com/municipio-se/modularity-graphql/compare/1.2.0...1.3.0) (2020-02-28)

## [1.2.0](https://github.com/municipio-se/modularity-graphql/compare/1.1.0...1.2.0) (2020-02-26)

## [1.1.0](https://github.com/municipio-se/modularity-graphql/compare/1.0.0...1.1.0) (2020-02-25)

## 1.0.0 (2020-02-24)

