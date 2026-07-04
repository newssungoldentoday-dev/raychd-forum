// Ray Chad Forum — script.js
// Small, dependency-free enhancements for the terminal-styled homepage.

(function () {
  "use strict";

  const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)"
  ).matches;

  /* ---------------------------------------------------------
     1. Typewriter effect for the terminal command line
     --------------------------------------------------------- */
  function typewriter() {
    const line = document.querySelector(".type-line");
    if (!line) return;

    const caret = line.querySelector(".caret");
    const fullText = line.getAttribute("data-text") || line.textContent.trim();

    // Cache the raw text once, then rebuild the line node structure.
    if (!line.dataset.cached) {
      line.dataset.cached = fullText.replace(/\s+$/, "");
    }
    const text = line.dataset.cached;

    if (prefersReducedMotion) {
      line.textContent = text;
      if (caret) line.appendChild(caret);
      return;
    }

    let i = 0;
    line.textContent = "";
    const speed = 28; // ms per character

    function type() {
      if (i <= text.length) {
        line.textContent = text.slice(0, i);
        if (caret) line.appendChild(caret);
        i++;
        setTimeout(type, speed);
      } else {
        // brief pause, then restart for a subtle looping feel
        setTimeout(() => {
          i = 0;
          type();
        }, 2600);
      }
    }
    type();
  }

  /* ---------------------------------------------------------
     2. Thread rows: simulate "new" activity ticking in
     --------------------------------------------------------- */
  function animateThreadMeta() {
    const rows = document.querySelectorAll(".thread-row .thread-meta");
    if (!rows.length || prefersReducedMotion) return;

    rows.forEach((meta, idx) => {
      const original = meta.textContent;
      meta.style.opacity = "0";
      setTimeout(() => {
        meta.style.transition = "opacity 400ms ease";
        meta.style.opacity = "1";
      }, 150 * idx);
      meta.textContent = original;
    });
  }

  /* ---------------------------------------------------------
     3. Smooth-scroll offset for sticky nav (in case anchor
        targets sit right under the sticky header)
     --------------------------------------------------------- */
  function fixAnchorOffsets() {
    const nav = document.querySelector("nav");
    if (!nav) return;
    const navHeight = nav.getBoundingClientRect().height;

    document.querySelectorAll('a[href^="#"]').forEach((link) => {
      link.addEventListener("click", (e) => {
        const id = link.getAttribute("href").slice(1);
        const target = document.getElementById(id);
        if (!target) return;
        e.preventDefault();
        const top =
          target.getBoundingClientRect().top +
          window.scrollY -
          navHeight -
          8;
        window.scrollTo({
          top,
          behavior: prefersReducedMotion ? "auto" : "smooth",
        });
      });
    });
  }

  /* ---------------------------------------------------------
     4. Copy-to-clipboard on install command blocks
     --------------------------------------------------------- */
  function enableCopyOnSteps() {
    document.querySelectorAll(".step code").forEach((codeEl) => {
      codeEl.style.cursor = "pointer";
      codeEl.setAttribute("title", "Tap to copy");
      codeEl.addEventListener("click", async () => {
        const text = codeEl.textContent;
        try {
          await navigator.clipboard.writeText(text);
          flashCopied(codeEl);
        } catch (err) {
          // Clipboard API unavailable or blocked — fail silently,
          // the command is still selectable by hand.
        }
      });
    });
  }

  function flashCopied(el) {
    const original = el.textContent;
    el.textContent = "✓ copied to clipboard";
    el.style.color = "var(--accent)";
    setTimeout(() => {
      el.textContent = original;
      el.style.color = "";
    }, 1200);
  }

  /* ---------------------------------------------------------
     Init
     --------------------------------------------------------- */
  document.addEventListener("DOMContentLoaded", () => {
    typewriter();
    animateThreadMeta();
    fixAnchorOffsets();
    enableCopyOnSteps();
  });
})();
