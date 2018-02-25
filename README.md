# Vue.js serverside template XSS example

This repository demonstrates how web apps that use both serverside rendering
and Vue.js can be vulnerable to XSS even if they take precautions.

`index.php` is a vulnerable PHP script. `fix-v-pre.php` and
`fix-servervars-global.php` are fixed versions of the vulnerable script.

The rest of this README walks through how to exploit the vulnerability, fixes
the vulnerability and then discusses the scope and impact of such a
vulnerability.

Note that this vulnerability is not specific to PHP nor is it specific to Vue.js.
If you have an app that mixes serverside rendering with clientside rendering,
you might be vulnerable.

I suggest that you run the demo, try to exploit it and then try to fix it. It's
a great learning experience.

## Running the demo

1.  Install [docker & docker-compose](https://docs.docker.com/install/)
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
dynamism to it. This is exactly what's happening here.

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
try to prevent you from accessing stuff outside your template data. This is just
a side effect of how Vue.js evaluates expressions.

How do we get out of this "sandbox"? There are many ways. If you want to flex
your javascript muscles you can give it a shot. The solution I went with is:

```
{{ constructor.constructor("alert('xss')")() }}
```

This looks obtuse but it's surprisingly simple. We know that we're evaluated
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

This exploit is possible becusae we're mixing serverside templating and
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

The simple fix for this is to use the
[`v-pre` directive](https://vuejs.org/v2/api/#v-pre) whenever you are injecting
serverside values into a clientside template.

In this case, you would change

```html
<div id="injectable-app">
  <div>
    You have injected:
    <?= htmlspecialchars($_GET['injectme']) ?>
  </div>

  <button type="button" @click="dec">-</button>
  {{counter}}
  <button type="button" @click="inc">+</button>
</div>
```

to

```html
<div id="injectable-app">
  <div v-pre>
    You have injected:
    <?= htmlspecialchars($_GET['injectme']) ?>
  </div>

  <button type="button" @click="dec">-</button>
  {{counter}}
  <button type="button" @click="inc">+</button>
</div>
```

While this solution does work, it's not great. It's easy for anyone to forget
to use the `v-pre` directive. If a single developer forgets to do this, you're
screwed all over again.

When it comes to security, I prefer systematic solutions. A better solution
would be to define a global variable in the page will all server side variables.
This does not prevent a developer from mixing serverside and clientside
templating but it does give them a secure mechanism for doing so.

We can implement this like so:

```html
<div id="injectable-app">
  <div>
    You have injected: {{ SERVER_VARS.injectMe }}
  </div>

  <button type="button" @click="dec">-</button>
  {{counter}}
  <button type="button" @click="inc">+</button>
</div>
<script src="https://cdn.jsdelivr.net/npm/vue@2.5.13/dist/vue.js"></script>
<?php
$serverVars = [
  'injectMe' => (string) $_GET['injectme']
];
?>
<script>
window.SERVER_VARS = <?= json_encode($serverVars) ?>;
Vue.prototype.SERVER_VARS = window.SERVER_VARS;
</script>
```

The full fix is available in `fix-servervars-global.php`.

## Is this a real threat?

After reading this, you might wonder: Why would anyone in their right minds mix
severside and clientside templating?

I think that it's pretty reasonable for a developer to add Vue.js to their
existing serverside rendering app and think that everything is going to be fine.
Vue.js advertises itself as a "progressive frameworks". They expect you to do
this. Also, the security risks are not immediately apparent. Getting to XSS was
pretty roundabout in this simple example.

If you do a little googling, you'll find a bunch of examples and tutorials on
how to use Vue.js with other serverside rendering frameworks. While I don't have
the numbers to back this, I think that there are plenty of apps out there that
mix serverside rendering and clientside templating. All of those apps could be
vulnerable XSS.

## Is this specific to PHP?

Not at all. This can work with any serverside language or technology. You can
take this example and rewrite it in any serverside technology and it would still
be vulnerable. It doesn't matter how much automatic escaping this technology
does, because no one automatically escapes variables injected in Vue.js
templates.

## What about other frameworks / libs?

Any library or framework that lets you write templates in HTML is potentially
vulnerable to this.

Angular 1 apps are famously vulnerable to this. In their
[security guide](https://docs.angularjs.org/guide/security#angularjs-templates-and-expressions)
the angular team warn explicitly against this.

> Generating AngularJS templates on the server containing user-provided content.
> This is the most common pitfall where you are generating HTML via some
> server-side engine such as PHP, Java or ASP.NET.

They've also tried for a long time to build a sandbox that mitigates against XSS
coming from the server. Every time that they've improved or fixed the sandbox,
it was broken or bypassed. Eventually the angular team
[got rid of the sandbox](https://docs.angularjs.org/guide/security#sandbox-removal).

Frameworks like React and Angular 2+ are not vulnerable to this kind of attack
because they don't let you write templates in HTML and they force the use of
a compiler. This makes injecting user input from the server into clientside
templates very unlikely.
