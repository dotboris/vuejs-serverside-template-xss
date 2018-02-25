<!DOCTYPE html>
<html>
<head>
  <title>VueJS serverside template xss</title>
</head>
<body>
  <ul>
    <li><a href="index.php">Broken version</a></li>
    <li><a href="fix-v-pre.php">Fix with v-pre</a></li>
    <li><a href="fix-servervars-global.php">Fix with global SERVER_VARS variable</a></li>
  </ul>

  <form action="">
    <label>
      <strong>Inject Here:</strong>
      <input
        type="text"
        name="injectme"
        value="<?= htmlspecialchars((string) $_GET['injectme']) ?>"
      />
      <button>Go!</button>
    </label>
  </form>

  <div id="injectable-app">
    <div>
      You have injected: {{ SERVER_VARS.injectMe }}
    </div>

    <button type="button" @click="dec">-</button>
    {{counter}}
    <button type="button" @click="inc">+</button>
  </div>

  <script>
    window.addEventListener('load', function () {
      new Vue({
        el: '#injectable-app',
        data: {
          counter: 0
        },
        methods: {
          inc: function () {
            ++this.counter;
          },

          dec: function () {
            --this.counter;
          }
        }
      });
    });
  </script>

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
</body>
</html>
