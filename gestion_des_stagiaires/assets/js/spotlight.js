/* Tiny mouse-tracker -> CSS variables for spotlight + tilt effects.
   Sets --mx/--my on :root (viewport coords) and on each [data-spotlight] (local %).
*/
(function(){
  var root = document.documentElement;
  window.addEventListener('mousemove', function(e){
    root.style.setProperty('--mx', e.clientX + 'px');
    root.style.setProperty('--my', e.clientY + 'px');
  }, { passive:true });

  document.querySelectorAll('[data-spotlight], .card--hover').forEach(function(el){
    el.addEventListener('mousemove', function(e){
      var r = el.getBoundingClientRect();
      el.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
      el.style.setProperty('--my', ((e.clientY - r.top)  / r.height * 100) + '%');
    });
  });
})();
