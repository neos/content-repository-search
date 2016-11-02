# TYPO3.TYPO3CR.Search

A TYPO3CR search common package used to implement concrete indexing and search functionality.

## Related packages

Some of the related packages are:

### [Flowpack.ElasticSearch.ContentRepositoryAdaptor](https://github.com/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor/)

To use Elasticsearch for indexing and searching.

### [Flowpack.SimpleSearch.ContentRepositoryAdaptor](https://github.com/kitsunet/Flowpack.SimpleSearch.ContentRepositoryAdaptor)

Uses a SQLite database for indexing and search and thus can be used without additional dependencies.

### [Flowpack.SearchPlugin](https://github.com/skurfuerst/Flowpack.SearchPlugin)

A plugin to offer search functionality to users via TypoScript rendering.

## Inner workings

The NodeIndexingManager listens to signals emitted from TYPO3CR and the PersistenceManager if
`realtimeIndexing.enabled` is `true` (which it defaults to).

- `nodeAdded`, `nodeUpdated`, `afterNodePublishing` trigger `indexNode()`
- `nodeRemoved` triggers `removeNode()`
- `allObjectsPersisted` triggers `flushQueues`

During a single request the queue with index changes is only flushed once the `indexingBatchSize`
has been reached (see `flushQueuesIfNeeded()`).

In case the operation queues are flushed, the `IndexingManager` in turn uses the `NodeIndexer`
to run `indexNode()` and `removeNode()` respectively.

If `realtimeIndexing.enabled` is `false`, the node index is only updated when built manually.
