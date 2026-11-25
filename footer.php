<?php if (($GLOBALS['__layout'] ?? 'app') === 'app'): ?>
    </main>
    <footer class="site-foot">Making Drugs and Vaccine Available To You</footer>
  </div>
</div>
<?php else: ?>
    </div>
  </section>
  <p class="auth-foot">Making Drugs and Vaccine Available To You</p>
</div>
<?php endif; ?>
<script>
(function () {
  // Show / hide password buttons
  document.querySelectorAll('input[type=password]').forEach(function (input) {
    var wrap = document.createElement('div');
    wrap.className = 'pw';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    btn.textContent = 'Show';
    btn.setAttribute('aria-label', 'Show password');
    btn.addEventListener('click', function () {
      var hidden = input.type === 'password';
      input.type = hidden ? 'text' : 'password';
      btn.textContent = hidden ? 'Hide' : 'Show';
      btn.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
    });
    wrap.appendChild(btn);
  });

  // Mobile sidebar
  var menu = document.getElementById('menuBtn'), scrim = document.getElementById('scrim');
  if (menu && scrim) {
    menu.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
    scrim.addEventListener('click', function () { document.body.classList.remove('nav-open'); });
  }
})();
</script>
</body>
</html>
