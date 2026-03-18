  </div><!-- /content -->
</div><!-- /main -->

<script>
function updateClock() {
  const now = new Date();
  document.getElementById('clock').textContent = now.toLocaleString('en-PH', {
    weekday:'short', month:'short', day:'numeric',
    hour:'2-digit', minute:'2-digit'
  });
}
updateClock();
setInterval(updateClock, 1000);
</script>
</body>
</html>
