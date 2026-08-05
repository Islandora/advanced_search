# Advanced Search Olivero

This compatibility submodule preserves the presentation that Advanced Search
2.x historically applied globally. It loads those styles only for Advanced
Search forms and Views while Olivero or an Olivero subtheme is active, leaving
other themes and unrelated page elements unaffected.

Existing sites with Olivero installed receive this submodule automatically
through an Advanced Search post-update hook. New Olivero sites can enable it
explicitly:

```shell
drush en advanced_search_olivero
```

The submodule has no effect when another theme is active.
