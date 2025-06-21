# Neos.ContentRepository.Search

[![Build Status](https://travis-ci.com/neos/content-repository-search.svg)](https://travis-ci.com/neos/content-repository-search) [![Latest Stable Version](https://poser.pugx.org/neos/content-repository-search/v/stable)](https://packagist.org/packages/neos/content-repository-search) [![Total Downloads](https://poser.pugx.org/neos/content-repository-search/downloads)](https://packagist.org/packages/neos/content-repository-search)

A Neos Content Repository search common package used to implement concrete indexing and search functionality.

## Related packages

Some of the related packages are:

### [Flowpack.ElasticSearch.ContentRepositoryAdaptor](https://github.com/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor/)

To use Elasticsearch for indexing and searching.

### [Flowpack.SimpleSearch.ContentRepositoryAdaptor](https://github.com/Flowpack/Flowpack.SimpleSearch.ContentRepositoryAdaptor.git)

Uses a SQLite database for indexing and search and thus can be used without additional dependencies.

### [Flowpack.SearchPlugin](https://github.com/Flowpack/Flowpack.SearchPlugin.git)

A plugin to offer search functionality to users via Fusion rendering.

## Realtime Indexing (experimental)

Realtime indexing in Neos.ContentRepository.Search >= 5.0 (compatible with Neos 9)
has SEVERE PERFORMANCE PENALTYS when publishing workspaces. We suggest that you do batch indexing
in the background instead.

To resolve backreferences (a very common Elasticsearch Use Case for Neos < 9), please now directly
use the backReferences() FlowQuery operation available in Neos 9.

If you still want to enable this, add the following to the Settings.yaml (but you have been warned).

```yaml
# Settings.yaml

Neos:
  ContentRepositoryRegistry:
    presets:
      'default':
        contentGraphProjection:
          catchUpHooks:
            'Neos.ContentRepository.Search:RealtimeIndex':
              factoryObjectName: \Neos\ContentRepository\Search\CatchUpHook\RealTimeIndexCatchUpHookFactory

```


## Inner workings

The NodeIndexingManager listens to signals emitted from Neos Content Repository and the PersistenceManager if
`realtimeIndexing.enabled` is `true` (which it defaults to).

- `nodeAdded`, `nodeUpdated`, `afterNodePublishing` trigger `indexNode()`
- `nodeRemoved` triggers `removeNode()`
- `allObjectsPersisted` triggers `flushQueues`

During a single request the queue with index changes is only flushed once the `indexingBatchSize`
has been reached (see `flushQueuesIfNeeded()`).

In case the operation queues are flushed, the `IndexingManager` in turn uses the `NodeIndexer`
to run `indexNode()` and `removeNode()` respectively.

If `realtimeIndexing.enabled` is `false`, the node index is only updated when built manually.
