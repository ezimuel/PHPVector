window.BENCHMARK_DATA = {
  "lastUpdate": 1788336064841,
  "repoUrl": "https://github.com/ezimuel/PHPVector",
  "entries": {
    "PHPVector Performance": [
      {
        "commit": {
          "author": {
            "email": "e.zimuel@gmail.com",
            "name": "Enrico Zimuel",
            "username": "ezimuel"
          },
          "committer": {
            "email": "noreply@github.com",
            "name": "GitHub",
            "username": "web-flow"
          },
          "distinct": true,
          "id": "5ba98eaca12d6e396e7c4f6c71bd4d2fd91b12dc",
          "message": "Merge pull request #4 from danielebarbaro/feat/metadata\n\nMetadata filter -  Adds metadata filtering support across all search methods",
          "timestamp": "2026-05-14T22:05:23+02:00",
          "tree_id": "955c43ff01001c487a92a57c951c9d0fb05f55c3",
          "url": "https://github.com/ezimuel/PHPVector/commit/5ba98eaca12d6e396e7c4f6c71bd4d2fd91b12dc"
        },
        "date": 1779814565382,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 19.53,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 226.69,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 389.81,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 117.82,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 17.11,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 658612.28,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 77.53,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "committer": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "distinct": true,
          "id": "402f9f79f42cf9d495831c7757c78253c25c3dd1",
          "message": "Fix benchmark workflow gh-pages baseline commit\n\nSet the git identity before committing the baseline so the\ngithub-actions runner can create the commit (was failing with\nfatal: empty ident name). Replace the silent 'git push ... || true'\nwith an explicit 'git push origin HEAD:gh-pages' so a rejected push\nfails the job instead of passing green.",
          "timestamp": "2026-05-26T19:09:49+02:00",
          "tree_id": "67f04ba7d64a33aab98c11410e2c8f2df63328ed",
          "url": "https://github.com/ezimuel/PHPVector/commit/402f9f79f42cf9d495831c7757c78253c25c3dd1"
        },
        "date": 1779817021896,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 19.98,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 223.79,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 392.97,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 121.36,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 16.57,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 683190.77,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 73.23,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "committer": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "id": "47c6218928df0acdbc19cd77919e844d8de22e06",
          "message": "Add php-cs-fixer with PER-CS 2.0 ruleset",
          "timestamp": "2026-05-26T17:13:43Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/9/commits/47c6218928df0acdbc19cd77919e844d8de22e06"
        },
        "date": 1779880398597,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 19.97,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 227.08,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 396.19,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 124.84,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 17.16,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 722359.11,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 78.4,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "committer": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "id": "099292df42cb5c64f19a1c21f718b6b72e92de36",
          "message": "PHPStan analysis to level 6",
          "timestamp": "2026-05-26T17:13:43Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/10/commits/099292df42cb5c64f19a1c21f718b6b72e92de36"
        },
        "date": 1779885919028,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 19.35,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 234.68,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 421.22,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 133.52,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 16.77,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 705718.29,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 79.24,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "committer": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "id": "539aea7d5946e66cc86167aa5fcb6e09d7c1dd71",
          "message": "Bump actions/checkout from 5 to 6",
          "timestamp": "2026-05-27T18:51:16Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/15/commits/539aea7d5946e66cc86167aa5fcb6e09d7c1dd71"
        },
        "date": 1779909289560,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 22.1,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 190.12,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 475.31,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 112.75,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 18.41,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 495748.71,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 68.72,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 28,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "danielebarbaro",
            "username": "danielebarbaro"
          },
          "committer": {
            "name": "danielebarbaro",
            "username": "danielebarbaro"
          },
          "id": "c97ce7a1428f6497f2ec086c3ac96296958c26d0",
          "message": "Test/hnsw coverage",
          "timestamp": "2026-05-27T19:51:54Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/16/commits/c97ce7a1428f6497f2ec086c3ac96296958c26d0"
        },
        "date": 1779913141051,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 19.48,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 212.47,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 418.9,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 129.64,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 16.79,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 784127.38,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 74.3,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "committer": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "id": "f8ba8d2a1e9c8e43dcde395d671069fae02f1a9a",
          "message": "Add isPersistent accessor to VectorDatabase",
          "timestamp": "2026-05-27T19:50:32Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/17/commits/f8ba8d2a1e9c8e43dcde395d671069fae02f1a9a"
        },
        "date": 1779961304015,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 19.85,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 222.66,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 390.61,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 123.89,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 17.54,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 700011.48,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 73.32,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "committer": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "distinct": true,
          "id": "62d972b5d09e3af629b4e858b2bd7ddbfefa632a",
          "message": "Strengthen round-trip and degree test assertions",
          "timestamp": "2026-05-28T16:24:40+02:00",
          "tree_id": "4a7226f13c868aa0570ac86f73324fb23369b3c4",
          "url": "https://github.com/ezimuel/PHPVector/commit/62d972b5d09e3af629b4e858b2bd7ddbfefa632a"
        },
        "date": 1779979386913,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 25.74,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 271.27,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 507.67,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 152.47,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 20.73,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 798276.36,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 87.95,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "committer": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "id": "a3264bf1bed695cb434321425506f152ef606091",
          "message": "Bump codecov/codecov-action from 6 to 7",
          "timestamp": "2026-05-28T14:25:17Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/18/commits/a3264bf1bed695cb434321425506f152ef606091"
        },
        "date": 1781098239608,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 18.6,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 225.27,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 413.72,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 126.29,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 16.19,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 785071.7,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 78.33,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "committer": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "id": "5bbf2e4ed2623d12fc83d635a004ca769a9a545f",
          "message": "Bump actions/cache from 5 to 6",
          "timestamp": "2026-06-17T12:47:36Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/20/commits/5bbf2e4ed2623d12fc83d635a004ca769a9a545f"
        },
        "date": 1782307702548,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 19.28,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 243.85,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 422.9,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 129.48,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 16.85,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 726042.42,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 76.24,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "email": "49699333+dependabot[bot]@users.noreply.github.com",
            "name": "dependabot[bot]",
            "username": "dependabot[bot]"
          },
          "committer": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "distinct": true,
          "id": "afc340e474514042bd95f056a895f0a9b7c65c29",
          "message": "Bump actions/cache from 5 to 6\n\nBumps [actions/cache](https://github.com/actions/cache) from 5 to 6.\n- [Release notes](https://github.com/actions/cache/releases)\n- [Changelog](https://github.com/actions/cache/blob/main/RELEASES.md)\n- [Commits](https://github.com/actions/cache/compare/v5...v6)\n\n---\nupdated-dependencies:\n- dependency-name: actions/cache\n  dependency-version: '6'\n  dependency-type: direct:production\n  update-type: version-update:semver-major\n...\n\nSigned-off-by: dependabot[bot] <support@github.com>",
          "timestamp": "2026-06-26T09:59:10+02:00",
          "tree_id": "2674f10bfefa32fe094f4de2af9905e2902b855b",
          "url": "https://github.com/ezimuel/PHPVector/commit/afc340e474514042bd95f056a895f0a9b7c65c29"
        },
        "date": 1782462165507,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 19.46,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 219.71,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 395.44,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 118.77,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 16.25,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 652453.62,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 54,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 72.19,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 28,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "email": "e.zimuel@gmail.com",
            "name": "Enrico Zimuel",
            "username": "ezimuel"
          },
          "committer": {
            "email": "noreply@github.com",
            "name": "GitHub",
            "username": "web-flow"
          },
          "distinct": true,
          "id": "1f5e159c35009902a7e8df38d16195fc462227ad",
          "message": "Merge pull request #19 from ezimuel/dependabot/github_actions/actions/checkout-7\n\nBump actions/checkout from 6 to 7",
          "timestamp": "2026-08-17T18:46:59+02:00",
          "tree_id": "4789f3eee4f3e3e28f5f7bfdbfdcda4be069808c",
          "url": "https://github.com/ezimuel/PHPVector/commit/1f5e159c35009902a7e8df38d16195fc462227ad"
        },
        "date": 1786986324474,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 24.76,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 262.03,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 516.66,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 152.39,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 20.56,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 863964.47,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 95.05,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "committer": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "id": "99d5a674ceba66f3b0fdeddf44d9cbdbb298aa33",
          "message": "Feat/multi process safety",
          "timestamp": "2026-08-17T16:48:09Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/21/commits/99d5a674ceba66f3b0fdeddf44d9cbdbb298aa33"
        },
        "date": 1788253544522,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 19.85,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 215.68,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 400.27,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 121.98,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 17.16,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 694629.2,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 74.24,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "committer": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "id": "e551037819d76aec39189e76b819d55d9d8dd112",
          "message": "Fix the benchmark comparison reporting a pass without comparing",
          "timestamp": "2026-08-17T16:48:09Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/22/commits/e551037819d76aec39189e76b819d55d9d8dd112"
        },
        "date": 1788257561405,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "insert (ops/s)",
            "value": 18.86,
            "unit": "ops/s"
          },
          {
            "name": "insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "vector_search (QPS)",
            "value": 222.92,
            "unit": "queries/s"
          },
          {
            "name": "vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "text_search (QPS)",
            "value": 420.77,
            "unit": "queries/s"
          },
          {
            "name": "text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "hybrid_search (QPS)",
            "value": 128.64,
            "unit": "queries/s"
          },
          {
            "name": "hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "update (ops/s)",
            "value": 16.15,
            "unit": "ops/s"
          },
          {
            "name": "update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "delete (ops/s)",
            "value": 666692,
            "unit": "ops/s"
          },
          {
            "name": "delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "open (MB/s)",
            "value": 75.24,
            "unit": "MB/s"
          },
          {
            "name": "open (memory delta)",
            "value": 26,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "committer": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "id": "e29b6831ff887362dd57334031fb5f3c1c31e15a",
          "message": "Feat/multi process safety",
          "timestamp": "2026-08-17T16:48:09Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/21/commits/e29b6831ff887362dd57334031fb5f3c1c31e15a"
        },
        "date": 1788261944041,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "xs/insert (ops/s)",
            "value": 19.62,
            "unit": "ops/s"
          },
          {
            "name": "xs/insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "xs/vector_search (QPS)",
            "value": 237.38,
            "unit": "queries/s"
          },
          {
            "name": "xs/vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/text_search (QPS)",
            "value": 423.94,
            "unit": "queries/s"
          },
          {
            "name": "xs/text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "xs/hybrid_search (QPS)",
            "value": 136.56,
            "unit": "queries/s"
          },
          {
            "name": "xs/hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/update (ops/s)",
            "value": 16.78,
            "unit": "ops/s"
          },
          {
            "name": "xs/update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "xs/delete (ops/s)",
            "value": 722295.98,
            "unit": "ops/s"
          },
          {
            "name": "xs/delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "xs/save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "xs/save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "xs/open (MB/s)",
            "value": 76.51,
            "unit": "MB/s"
          },
          {
            "name": "xs/open (memory delta)",
            "value": 26,
            "unit": "MB"
          },
          {
            "name": "small/insert (ops/s)",
            "value": 19,
            "unit": "ops/s"
          },
          {
            "name": "small/insert (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/vector_search (QPS)",
            "value": 219.04,
            "unit": "queries/s"
          },
          {
            "name": "small/vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/text_search (QPS)",
            "value": 423.19,
            "unit": "queries/s"
          },
          {
            "name": "small/text_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/hybrid_search (QPS)",
            "value": 135.31,
            "unit": "queries/s"
          },
          {
            "name": "small/hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/update (ops/s)",
            "value": 17.3,
            "unit": "ops/s"
          },
          {
            "name": "small/update (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/delete (ops/s)",
            "value": 632880.15,
            "unit": "ops/s"
          },
          {
            "name": "small/delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "small/save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "small/save (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "small/open (MB/s)",
            "value": 61.39,
            "unit": "MB/s"
          },
          {
            "name": "small/open (memory delta)",
            "value": 2,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "committer": {
            "name": "ezimuel",
            "username": "ezimuel"
          },
          "id": "eae46b2332617d5f99ed891e43d56b0ab88941f2",
          "message": "Stop pull requests writing the benchmark baseline and holding a write token",
          "timestamp": "2026-09-01T10:43:47Z",
          "url": "https://github.com/ezimuel/PHPVector/pull/23/commits/eae46b2332617d5f99ed891e43d56b0ab88941f2"
        },
        "date": 1788268957023,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "xs/insert (ops/s)",
            "value": 19.25,
            "unit": "ops/s"
          },
          {
            "name": "xs/insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "xs/vector_search (QPS)",
            "value": 219.41,
            "unit": "queries/s"
          },
          {
            "name": "xs/vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/text_search (QPS)",
            "value": 421.96,
            "unit": "queries/s"
          },
          {
            "name": "xs/text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "xs/hybrid_search (QPS)",
            "value": 128.99,
            "unit": "queries/s"
          },
          {
            "name": "xs/hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/update (ops/s)",
            "value": 17.39,
            "unit": "ops/s"
          },
          {
            "name": "xs/update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "xs/delete (ops/s)",
            "value": 691320.06,
            "unit": "ops/s"
          },
          {
            "name": "xs/delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "xs/save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "xs/save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "xs/open (MB/s)",
            "value": 75.6,
            "unit": "MB/s"
          },
          {
            "name": "xs/open (memory delta)",
            "value": 26,
            "unit": "MB"
          },
          {
            "name": "small/insert (ops/s)",
            "value": 19.3,
            "unit": "ops/s"
          },
          {
            "name": "small/insert (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/vector_search (QPS)",
            "value": 231.96,
            "unit": "queries/s"
          },
          {
            "name": "small/vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/text_search (QPS)",
            "value": 419.33,
            "unit": "queries/s"
          },
          {
            "name": "small/text_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/hybrid_search (QPS)",
            "value": 131.35,
            "unit": "queries/s"
          },
          {
            "name": "small/hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/update (ops/s)",
            "value": 16.6,
            "unit": "ops/s"
          },
          {
            "name": "small/update (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/delete (ops/s)",
            "value": 661261.08,
            "unit": "ops/s"
          },
          {
            "name": "small/delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "small/save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "small/save (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "small/open (MB/s)",
            "value": 66.97,
            "unit": "MB/s"
          },
          {
            "name": "small/open (memory delta)",
            "value": 2,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "committer": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "distinct": true,
          "id": "507e37d16d278eb53c26f649a5787e8886e94375",
          "message": "Split the privileged half of the benchmark workflow\n\nThe workflow ran on pull_request_target with contents, issues and\npull-requests write, checked out the head of the pull request and executed\ncomposer install and the benchmark from it. That grants a writable token to\ncode supplied by anyone who can open a pull request.\n\nThe measurement now runs on pull_request with a read-only token and only\nuploads its numbers as an artifact. A second workflow, triggered by\nworkflow_run, checks out the default branch, downloads that artifact and\nposts the comment. Nothing from the branch under review is executed with\nwrite access, and the pull request number read from the artifact is\nvalidated before use.",
          "timestamp": "2026-09-01T15:32:08+02:00",
          "tree_id": "057fde6c0579e52ce27fe8a51541e5575682e9fd",
          "url": "https://github.com/ezimuel/PHPVector/commit/507e37d16d278eb53c26f649a5787e8886e94375"
        },
        "date": 1788271260558,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "xs/insert (ops/s)",
            "value": 35.95,
            "unit": "ops/s"
          },
          {
            "name": "xs/insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "xs/vector_search (QPS)",
            "value": 400.26,
            "unit": "queries/s"
          },
          {
            "name": "xs/vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/text_search (QPS)",
            "value": 675.31,
            "unit": "queries/s"
          },
          {
            "name": "xs/text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "xs/hybrid_search (QPS)",
            "value": 209.41,
            "unit": "queries/s"
          },
          {
            "name": "xs/hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/update (ops/s)",
            "value": 31.08,
            "unit": "ops/s"
          },
          {
            "name": "xs/update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "xs/delete (ops/s)",
            "value": 1026443.23,
            "unit": "ops/s"
          },
          {
            "name": "xs/delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/save (MB/s)",
            "value": 0.03,
            "unit": "MB/s"
          },
          {
            "name": "xs/save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "xs/save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "xs/open (MB/s)",
            "value": 117.8,
            "unit": "MB/s"
          },
          {
            "name": "xs/open (memory delta)",
            "value": 26,
            "unit": "MB"
          },
          {
            "name": "small/insert (ops/s)",
            "value": 35.4,
            "unit": "ops/s"
          },
          {
            "name": "small/insert (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/vector_search (QPS)",
            "value": 391.46,
            "unit": "queries/s"
          },
          {
            "name": "small/vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/text_search (QPS)",
            "value": 677.01,
            "unit": "queries/s"
          },
          {
            "name": "small/text_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/hybrid_search (QPS)",
            "value": 205.78,
            "unit": "queries/s"
          },
          {
            "name": "small/hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/update (ops/s)",
            "value": 31.13,
            "unit": "ops/s"
          },
          {
            "name": "small/update (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/delete (ops/s)",
            "value": 1194185.27,
            "unit": "ops/s"
          },
          {
            "name": "small/delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "small/save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "small/save (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "small/open (MB/s)",
            "value": 96.71,
            "unit": "MB/s"
          },
          {
            "name": "small/open (memory delta)",
            "value": 2,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "committer": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "distinct": true,
          "id": "aeed263ab4e44e1d66cf457bcc8dcbf50ce126cd",
          "message": "Explain why benchmark thresholds differ by metric\n\nDocuments the two thresholds with the measured variance that motivates\nthem, and states plainly that the CI comparison does not replace repeated\nruns on fixed hardware.",
          "timestamp": "2026-09-01T17:28:19+02:00",
          "tree_id": "b076b649103fe445f1f697c3f155f33d331b7b80",
          "url": "https://github.com/ezimuel/PHPVector/commit/aeed263ab4e44e1d66cf457bcc8dcbf50ce126cd"
        },
        "date": 1788279401237,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "xs/insert (ops/s)",
            "value": 19.1,
            "unit": "ops/s"
          },
          {
            "name": "xs/insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "xs/vector_search (QPS)",
            "value": 208.18,
            "unit": "queries/s"
          },
          {
            "name": "xs/vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/text_search (QPS)",
            "value": 403.13,
            "unit": "queries/s"
          },
          {
            "name": "xs/text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "xs/hybrid_search (QPS)",
            "value": 115.57,
            "unit": "queries/s"
          },
          {
            "name": "xs/hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/update (ops/s)",
            "value": 16.4,
            "unit": "ops/s"
          },
          {
            "name": "xs/update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "xs/delete (ops/s)",
            "value": 624194.4,
            "unit": "ops/s"
          },
          {
            "name": "xs/delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "xs/save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "xs/save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "xs/open (MB/s)",
            "value": 71.31,
            "unit": "MB/s"
          },
          {
            "name": "xs/open (memory delta)",
            "value": 26,
            "unit": "MB"
          },
          {
            "name": "small/insert (ops/s)",
            "value": 18.99,
            "unit": "ops/s"
          },
          {
            "name": "small/insert (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/vector_search (QPS)",
            "value": 177.32,
            "unit": "queries/s"
          },
          {
            "name": "small/vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/text_search (QPS)",
            "value": 399.87,
            "unit": "queries/s"
          },
          {
            "name": "small/text_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/hybrid_search (QPS)",
            "value": 108.81,
            "unit": "queries/s"
          },
          {
            "name": "small/hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/update (ops/s)",
            "value": 16.19,
            "unit": "ops/s"
          },
          {
            "name": "small/update (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/delete (ops/s)",
            "value": 569472.51,
            "unit": "ops/s"
          },
          {
            "name": "small/delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "small/save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "small/save (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "small/open (MB/s)",
            "value": 58.41,
            "unit": "MB/s"
          },
          {
            "name": "small/open (memory delta)",
            "value": 2,
            "unit": "MB"
          }
        ]
      },
      {
        "commit": {
          "author": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "committer": {
            "email": "barbaro.daniele@gmail.com",
            "name": "Daniele Barbaro",
            "username": "danielebarbaro"
          },
          "distinct": true,
          "id": "6ff5ff6566f43f281232854907717346cf5037ac",
          "message": "Fix broken examples and paths in the docs",
          "timestamp": "2026-09-02T09:12:33+02:00",
          "tree_id": "47687fe89fa374535ce6c424e264ca0a5cafe99b",
          "url": "https://github.com/ezimuel/PHPVector/commit/6ff5ff6566f43f281232854907717346cf5037ac"
        },
        "date": 1788336064335,
        "tool": "customBiggerIsBetter",
        "benches": [
          {
            "name": "xs/insert (ops/s)",
            "value": 19.83,
            "unit": "ops/s"
          },
          {
            "name": "xs/insert (memory delta)",
            "value": 48,
            "unit": "MB"
          },
          {
            "name": "xs/vector_search (QPS)",
            "value": 214.27,
            "unit": "queries/s"
          },
          {
            "name": "xs/vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/text_search (QPS)",
            "value": 432.35,
            "unit": "queries/s"
          },
          {
            "name": "xs/text_search (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "xs/hybrid_search (QPS)",
            "value": 133.2,
            "unit": "queries/s"
          },
          {
            "name": "xs/hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/update (ops/s)",
            "value": 16.78,
            "unit": "ops/s"
          },
          {
            "name": "xs/update (memory delta)",
            "value": 8,
            "unit": "MB"
          },
          {
            "name": "xs/delete (ops/s)",
            "value": 802015.3,
            "unit": "ops/s"
          },
          {
            "name": "xs/delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "xs/save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "xs/save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "xs/save (memory delta)",
            "value": 56,
            "unit": "MB"
          },
          {
            "name": "xs/open (MB/s)",
            "value": 77.89,
            "unit": "MB/s"
          },
          {
            "name": "xs/open (memory delta)",
            "value": 26,
            "unit": "MB"
          },
          {
            "name": "small/insert (ops/s)",
            "value": 19.39,
            "unit": "ops/s"
          },
          {
            "name": "small/insert (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/vector_search (QPS)",
            "value": 231.48,
            "unit": "queries/s"
          },
          {
            "name": "small/vector_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/text_search (QPS)",
            "value": 415.33,
            "unit": "queries/s"
          },
          {
            "name": "small/text_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/hybrid_search (QPS)",
            "value": 127.7,
            "unit": "queries/s"
          },
          {
            "name": "small/hybrid_search (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/update (ops/s)",
            "value": 16.89,
            "unit": "ops/s"
          },
          {
            "name": "small/update (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/delete (ops/s)",
            "value": 675384.53,
            "unit": "ops/s"
          },
          {
            "name": "small/delete (memory delta)",
            "value": 0,
            "unit": "MB"
          },
          {
            "name": "small/save (MB/s)",
            "value": 0.02,
            "unit": "MB/s"
          },
          {
            "name": "small/save (disk size)",
            "value": 13.12,
            "unit": "MB"
          },
          {
            "name": "small/save (memory delta)",
            "value": 2,
            "unit": "MB"
          },
          {
            "name": "small/open (MB/s)",
            "value": 65.73,
            "unit": "MB/s"
          },
          {
            "name": "small/open (memory delta)",
            "value": 2,
            "unit": "MB"
          }
        ]
      }
    ]
  }
}