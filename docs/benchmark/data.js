window.BENCHMARK_DATA = {
  "lastUpdate": 1781098239961,
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
      }
    ]
  }
}