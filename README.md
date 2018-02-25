# Vue.js serverside template XSS example

TODO: What is this?

## Running the demo

1.  Install [docker & docker-compsoe](https://docs.docker.com/install/)
1.  Run the demo

    ```sh
    docker-compose up -d
    ```

1.  Open <http://localhost:8080> in your browser

If you don't want to bother with `docker` you can throw the `index.php` file on
a php capable server and host it there. Keep in mind that this file is
vulnerable to XSS by design, you should run this on a local environment.

## Walkthrough

:rotating_light: Warning: spoilers ahead! If you want to exploit this
yourself, stop reading. The following gives the solution. :rotating_light:

Once you open the app, you see that you have two things to play with:

1.  a textbox that lets you inject text
1.  a counter application

When we play around with the textbox, see that whatever we put in there ends up
as a query parameter. If we put `foobar` in the textbox, we end up with the
following url: `http://localhost:8080/?injectme=foobar`. We can also see that
the text `foobar` is injected in the page.

This injection looks to be done server side. We can confirm this by looking at
the source of the page. We see that `foobar` was part of the response sent by
the server.

We have an opportunity for XSS here. Let's try the usual bag of tricks.

When we try `<script>alert('xss')</script>` we get
`&lt;script&gt;alert('xss')&lt;/script&gt;`.
Similarly, if we try `<img src="nope.jpg" onerror="alert('xss')"/>` we get
`&lt;img src=&quot;nope.jpg&quot; onerror=&quot;alert('xss')&quot;/&gt;`.

Looks like everything gets escaped properly. We can confirm this by looking at
the source code of the page.

Back to the drawing board. Let's try to understand how this app works. How does
the counter app work? Maybe it can help us here.

The counter app is built with Vue.js. For those who are not familiar. Vue.js is
a javascript lib that runs it the browser. It lets you build dynamic frontend
applications.

One way of using Vue.js is to write a template in the HTML of your page and then
tell Vue.js to render it through javascript. This is a common thing to do when
you have an application that uses serverside rendering and you want to add some
dynamysm to it. This is exacly what's happening here.

When we look at the template, we see that our injected value from before is
rendered directly inside of the template.

```html
<div id="injectable-app">
  <div>
    You have injected: OUR_INJECTED_VALUE_GOES_HERE
  </div>

  <button type="button" @click="dec">-</button>
  {{counter}}
  <button type="button" @click="inc">+</button>
</div>
```

Maybe we can exploit this. Vue.js templates allow you to use expressions.
Expressions are bits of code that take some data, transform it and output it.
They're basically javascript. In Vue.js expressions have the form
`{{ ... code goes here ... }}`.

Let's give this a shot. We write `{{ 2 + 2 }}` in the textbox and we get
`You have injected: 4`. It works!

For good measure we'll do an `alert` to prove that we have full control. We put
`{{ alert('xss') }}` in the textbox and nothing. The whole counter is gone. When
we look at the console we get an error:

```
TypeError: alert is not a function
    at Proxy.eval (eval at createFunction (vue.js:10518), <anonymous>:2:114)
    at Vue$3.Vue._render (vue.js:4465)
    at Vue$3.updateComponent (vue.js:2765)
    at Watcher.get (vue.js:3113)
    at new Watcher (vue.js:3102)
    at mountComponent (vue.js:2772)
    at Vue$3.$mount (vue.js:8416)
    at Vue$3.$mount (vue.js:10777)
    at Vue$3.Vue._init (vue.js:4557)
    at new Vue$3 (vue.js:4646)
```

`alert` is not a function? What's going on here? Vue.js expressions are
evaluated in the context of the `Vue` instance that they are rendered with.
In other words, when we try to render `{{ foobar }}`, it looks for the `foobar`
property in the template's data. When we did `{{ alert('xss') }}` it was the
same thing as doing `templateData.alert('xss')`. We got the error because our
template data does not have a property named `alert`.

You can think of this as being stuck inside a javascript jail or sandbox. It's
important to note that Vue.js doesn't have a real sandbox. It doesn't actively
try to prevent you from acessing stuff outside your template data. This is just
a side effect of how Vue.js evaluates expressions.

How do we get out of this "sandbox"? There are many ways. If you want to flex
your javascript mustles you can give it a shot. The solution I went with is:

```
{{ constructor.constructor("alert('xss')")() }}
```

This looks obtuse but it's surprisinsly simple. We know that we're evaluated
against our template data. When we do `constructor`, it's the same
as doing `templateData.constructor`. Our template data is an object. All objects
in javascript have a constructor. So `constructor` gives us `Vue$3` (the Vue.js
constructor).

In javascript, all constructors are functions and all functions are objects.
This means that `Vue$3` has a constructor. This constructor is the `Function`
constructor.

The `Function` constructor let's us define a function dynamically at runtime.
We pass it the code of our function and it returns a function that we can run.
In this case we we do `Function("alert('xss')")()`. We create a function that
calls `alert` (the real `alert` in the global scope) and call it.

That's it. We've made it, we have injected javascript in a page we don't control
and this javascript has access to the global scope. At this point we can do
anything the browser can. This is full blown XSS.

## Why does this work?

This exploit is possible becuase we're mixing serverside templating and
clientside templating.

In this case, we have our PHP app that takes user input (a query parameter) and
uses it to render an HTML page. The app escapes the input for HTML entities
ensuring that simple XSS is impossible. When the page makes it into the browser,
Vue.js takes part of this HTML and renders it like a template. We've seen that
it's basically doing a complex `eval` on that HTML.

In this context, Vue.js can't tell the difference between the user input which
may not be safe and the template which is essentially code and is considered
safe.

When it's able to tell the difference between user input and template code,
Vue.js does an amazing job at preventing XSS. In fact, it does a better job than
PHP because it will treat it's template data as dangerous by default and always
escape it. You don't have to remember to escape your data.

## How can I protect against this?

## Is this a real threat?

## Is this specific to PHP?

## What about other frameworks / libs?
