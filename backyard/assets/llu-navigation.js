(() => {
  'use strict';
  const btn=document.getElementById('llu-menu-button');
  const backdrop=document.getElementById('llu-menu-backdrop');
  const side=document.getElementById('llu-sidebar');
  if(!btn||!side||!backdrop)return;
  const setOpen=open=>{
    document.body.classList.toggle('llu-nav-open',open);
    btn.setAttribute('aria-expanded',String(open));
    btn.setAttribute('aria-label',open?'Navigation schließen':'Navigation öffnen');
    backdrop.hidden=!open;
    if(open){side.querySelector('[aria-current="page"]')?.focus({preventScroll:true});}
    else{btn.focus({preventScroll:true});}
  };
  btn.addEventListener('click',()=>setOpen(!document.body.classList.contains('llu-nav-open')));
  backdrop.addEventListener('click',()=>setOpen(false));
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.body.classList.contains('llu-nav-open'))setOpen(false)});
  side.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{
    document.body.classList.remove('llu-nav-open');backdrop.hidden=true;
  }));
})();

/* ============================================
   LLU 27 – Desktop Sidebar Toggle
   ============================================ */

document.addEventListener("DOMContentLoaded", function () {

    const body = document.body;
    const sidebar = document.querySelector(".llu-sidebar");

    if (!sidebar || !body.classList.contains("llu-layout")) {
        return;
    }

    // Desktop-Button erstellen
    const toggle = document.createElement("button");

    toggle.type = "button";
    toggle.className = "llu-desktop-toggle";
    toggle.setAttribute("aria-label", "Sidebar einklappen");
    toggle.setAttribute("aria-expanded", "true");

    toggle.textContent = "❮";

    document.body.appendChild(toggle);

    // Gespeicherten Zustand laden
    let collapsed = false;

    try {
        collapsed =
            localStorage.getItem("llu-sidebar-collapsed") === "1";
    } catch (error) {
        // Funktioniert auch ohne Local Storage
    }

    function updateSidebar() {

        body.classList.toggle(
            "llu-sidebar-collapsed",
            collapsed
        );

        toggle.textContent = collapsed ? "❯" : "❮";

        toggle.setAttribute(
            "aria-expanded",
            String(!collapsed)
        );

        toggle.setAttribute(
            "aria-label",
            collapsed
                ? "Sidebar ausklappen"
                : "Sidebar einklappen"
        );
    }

    // Beim Klicken Zustand ändern
    toggle.addEventListener("click", function () {

        collapsed = !collapsed;

        updateSidebar();

        try {
            localStorage.setItem(
                "llu-sidebar-collapsed",
                collapsed ? "1" : "0"
            );
        } catch (error) {
            // Speicherung ist optional
        }

        // Diagramme nach der Animation neu skalieren
        setTimeout(function () {
            window.dispatchEvent(new Event("resize"));
        }, 300);
    });

    updateSidebar();
});
