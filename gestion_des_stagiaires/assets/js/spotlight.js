/* Mouse-tracker with lerp smoothing -> CSS variables for spotlight + tilt effects.
   Sets --mouse-x/--mouse-y on :root (viewport px, smoothed).
   Sets --mx/--my on each [data-spotlight] / .card--hover (local %).
*/
(function () {
  var root = document.documentElement;

  // Target (raw mouse) and current (lerped) positions
  var targetX = window.innerWidth  / 2;
  var targetY = window.innerHeight / 2;
  var currentX = targetX;
  var currentY = targetY;

  // Lerp factor: 0.08 = smooth and fluid, raise toward 1 for snappier
  var LERP = 0.08;

  window.addEventListener("mousemove", function (e) {
    targetX = e.clientX;
    targetY = e.clientY;
  }, { passive: true });

  // Smoothing loop
  function tick() {
    currentX += (targetX - currentX) * LERP;
    currentY += (targetY - currentY) * LERP;

    root.style.setProperty("--mouse-x", currentX + "px");
    root.style.setProperty("--mouse-y", currentY + "px");
    // Keep --mx / --my in sync for any code that uses them
    root.style.setProperty("--mx", currentX + "px");
    root.style.setProperty("--my", currentY + "px");

    requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);

  // Per-element spotlight (local %) -- still instant is fine here
  document.querySelectorAll("[data-spotlight], .card--hover").forEach(function (el) {
    el.addEventListener("mousemove", function (e) {
      var r = el.getBoundingClientRect();
      el.style.setProperty("--mx", ((e.clientX - r.left) / r.width  * 100) + "%");
      el.style.setProperty("--my", ((e.clientY - r.top)  / r.height * 100) + "%");
    });
  });
})();

