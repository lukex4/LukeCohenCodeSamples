# WordPress Tag Priority Flag

This plugin enables an author to tell WordPress that a post has 'priority' in a given tag or other taxonomy.

## Adding priority status to a tag

![Image of priority flagging a tag](https://raw.githubusercontent.com/lukenicohen/wp-tag-priority/master/tag-flag.jpg)

To flag a post's tag as having priority in that tag, you simply click the grey exclamation mark icon in the tag on the post editor. If the icon turns red, it indicates priority.

## How it works, under the hood

*This is a technical explanation, and can be ignored unless you want to specifically know how the plugin handles priority flagging tags.*

The way this plugin adds a priority flag is by creating a 'shadow taxonomy', a secret other tag which the user (and author) never sees. When an author gives a post a tag, e.g 'christmas-feature', and then assigns the priority flag to that tag, the plugin adds a secret ('taxonomy_priority') tag in the background with the following name:

> post_tag__christmas-feature

There are two parts to this tag, the first, before __ is the taxonomy type slug. WordPress has many types of taxonomy, including basic tags, custom types of tags and categories. The taxonomy type slug goes first, as when querying later on we need to only retrieve priority tags of the type we wish.

The second part of that tag is 'christmas-feature', which is the slug of the tag we wish to apply priority status to.

## Retrieving posts that have a tag and priority in that tag

When building a template, you can retrieve priority flagged content by setting up a special loop with a 'relative' WordPress taxonomy query. You are querying for two pieces of taxonomy, the actual top-level taxonomy of the view itself (i.e christmas-feature), and adding a second part to your query, which looks for the priority flag.

Here's an example query which retrieves all posts tagged christmas-feature and marked priority in that tag.

```
<?php

    $args = array(

        'post_type'    => 'post',
        'order_by'     => 'date',
        'order'        => 'DESC',

        'tax_query'     => array(
        'relation'      => 'AND',

        array (
            'taxonomy'  => 'post_tag',
            'field'     => 'slug',
            'terms'     => 'christmas-feature'
        ),

        array(
            'taxonomy'  => 'taxonomy_priority',
            'field'     => 'slug',
            'terms'     => 'post_tag__christmas-feature'
        ),

        )

    );

    $loop = new WP_Query( $args );

    while ( $loop->have_posts() ) {

        $loop->the_post();
        get_template_part('partials/loop-post');

    }

?>
```
