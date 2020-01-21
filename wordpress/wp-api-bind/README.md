# Immediate API Binder Plugin

General purpose plugin which enables binding of API JSON responses to posts and pages.

## Setting up an API binding

Before an API can be bound to a page or post, it must be created as a 'binding' option. Once API Bind has been installed, in the WordPress admin panel click Tools -> API Bindings. You will be presented with a form to create new bindings, as well as a list of existing bindings below.

![Image of API bindings screen](https://raw.githubusercontent.com/lukex4/wp-api-bind/master/wp-api-bind/inc/1.png)

To create a new API binding you will need some basic information about the API. Does the API require a HTTP GET or HTTP POST? What's the base URI of the API endpoint you want to call? Enter those details in the form, and give this binding a name (the name is used elsewhere so make it something memorable and unique to that API binding), e.g Episode API Lookup.

**API Bind plugin only works with JSON API endpoints.**

The following is an example of a Base URI you might enter:

> http://some.api.com/endpoints/endpoint1/

HTTP GET endpoints shouldn't have a trailing '?', as API Bind adds them automatically where appropriate.

Don't save the binding just yet...

##### Adding fields to an API binding

Most APIs require input in order to return the relevant information. API Bind passes these inputs as fields, either as part of the HTTP GET URI, or as POST query variables. These fields can be fixed or dynamic, depending on your needs.

Click 'Add a field +' to add your first field to an API binding. You will now be presented with a small form with two fields: 'Field name' and 'Default value'. Field name is what will be passed to the API. For example if the API endpoint you are binding to is a HTTP GET endpoint, with a simple URI like:

> http://some.api.com/endpoints/endpoint1/?articleType=episodes&episodeID=potato

You would set up two fields for your new binding. The Field name for the first field would be 'episodes' (case-sensitive), and the Field name for the second field would be 'episodeID'. If you provide default values, those will be saved and made available when you bind a specific page to this API (explained later on in this document).

## Save your API binding

Once you've added the relevant basic information and any optional fields you wish to add, click 'Save and create binding'. Your binding will now be saved and you can go to the next stage of configuration, in a specific page.

## Binding a page to an API

Pages are the main focus of this introduction, as I feel it will be more common to bind a page to an API call rather than a post. An example of how this might be relevant: Say you have an Episode page which, when passed an epID, calls an API to retrieve the details of a specific episode of a specific TV show. The WordPress page would have a specific template, and would be set up to handle the specific of the API call.

![Image of page-API binding options](https://raw.githubusercontent.com/lukex4/wp-api-bind/master/wp-api-bind/inc/2.png)

In WordPress, click Pages->Add New. Enter a basic title (e.g Episode Lookup). On the right sidebar you will see a box titled 'API Data Sources', which contains a dropdown with all the API Bindings you have set up on the previous screen. Now if you click 'Episode API Lookup' from the dropdown, you will see options appear for mapping values to the fields you created for this API binding.

You will see a row for each field available in the binding. For example if you were setting up an episode lookup as above, you would see the field 'episodeID', as well as some options. These options allow you to tell API Bind what sort of value to pass to the API in that episodeID field.

##### Default value

If you select 'Default value', episodeID will be populated by the default value you entered in the field creation (if you entered a default value).

##### Explicit value

If you select 'Explicit value', episodeID will be populated with whatever you enter in the associated text box.

##### HTTP GET var

If you select 'HTTP GET var', you will be able to have episodeID populated with a dynamic value. This is particularly useful when you want a page to render API information for various datasets (episodes, in our case). With this option, you must name the HTTP GET var in the text box. API Bind will look for a GET variable with this name, and will pass the value of that GET variable through to the API as the episodeID value.

In our case we will use a HTTP GET variable named 'epID', so requests to this page with epID=XXX will map the value of epID to the episodeID field in the API binding we set up earlier.

## Caching

If you wish, you can tell API Bind to cache the results of identical API calls for as long or as short as you'd like. In the 'Cache this API Call' form, enter a number of minutes if you want to enable caching, or leave it as zero if you want no caching at all.

Calls are cached dynamically if a HTTP GET variable is used as a field mapping value (each different entry to a GET variable would trigger its own cache of the API response).

## Page templates to render API objects

You should set up a specific WordPress page template for this view, as a chunk of PHP is required in the template to tie a few things up. There is an example page template in the plugin folder. If you wish, you can just paste the following block of PHP at the top of an existing WordPress page template:

```
/* Retrieve API response if there is a binding */
if (class_exists('WPAPIBind')) {

  $post_id = $post->ID;
  $meta = get_post_meta($post_id);

  $api = WPAPIBind::retrieveAPIResponseObject($post_id, $meta, false);

  if ($api===false) {
    $api = Array();
  }

}
```

This block of code, if it runs successfully once everything is set up, will return an array $api which will mirror the API response. You may access that $api array directly in your template, and do whatever you want with it, treating it as you would a local object.

## Passing an API response object to Twig

If you are using Twig and wish to pass an API response object to Timber for use in a template, add the following line of code before your *Timber::render* call:

```
$context['apiResponse'] = (array)$api;
```

Now, in your Twig template, you can access the API response object as you would any other, for example:

```
<!-- Set your background image for this header on the line below. -->
<header class="intro-header" style="background-image: url('')">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1">
                <div class="page-heading">

                    <h1>{{apiResponse.Episode.BrandTitle}}</h1>

                    <hr class="small">

                    <span class="subheading">{{apiResponse.Episode.Title}}</span>

                </div>
            </div>
        </div>
    </div>
</header>


<!-- Main Content -->
<div class="container">
    <div class="row">
        <div class="col-lg-8 col-lg-offset-2 col-md-10 col-md-offset-1">

          <p>

            <img src="{{apiResponse.Episode.ImageUri}}" class="img-responsive" />

          </p>

            {{apiResponse.Episode.Summary}}

        </div>
    </div>
</div>
```

## Publish the page

Once you've filled in the API field mapping info accordingly, Publish the page.

Now if you load the page, and pass it the appropriate value via the HTTP GET variable, you will be presented with a view that contains the response from the API:

http://my.site/episode-lookup/?epID=d5c28m

![Image of page rendering with an API binding](https://raw.githubusercontent.com/lukex4/wp-api-bind/master/wp-api-bind/inc/3.png)
