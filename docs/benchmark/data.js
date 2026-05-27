window.BENCHMARK_DATA = {
  "lastUpdate": 1779886161631,
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
      }
    ]
  }
}