// Ray Chad Forum — manifest.js
// Builds the Web App Manifest at runtime and injects it as a Blob URL,
// so the whole PWA (including icons) lives in this one file — no
// separate manifest.json or icon assets to keep in sync.

(function () {
  "use strict";

  /* ---------------------------------------------------------------------
     Icon: a simple terminal-glyph mark, drawn as SVG and embedded
     directly as a base64 data URI. Swap this for your own artwork by
     replacing the <svg>...</svg> string below.
     --------------------------------------------------------------------- */
  const ICON_SVG = `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
      <rect width="512" height="512" rx="96" fill="#0d0f0d"/>
      <text x="50%" y="54%" text-anchor="middle" dominant-baseline="middle"
            font-family="ui-monospace, Menlo, Consolas, monospace"
            font-size="220" font-weight="700" fill="#7fd66b">#</text>
    </svg>
  `.trim();

  function svgToDataUri(svg) {
    const base64 = typeof btoa === "function"
      ? btoa(unescape(encodeURIComponent(svg)))
      : Buffer.from(svg, "utf-8").toString("base64");
    return `data:image/svg+xml;base64,${base64}`;
  }

  const iconDataUri = svgToDataUri(ICON_SVG);

  /* ---------------------------------------------------------------------
     Manifest definition
     --------------------------------------------------------------------- */
  const manifest = {
    name: "Ray Chad Forum",
    short_name: "RC Forum",
    description: "Threaded, self-hosted discussion for Ray Chad IRC communities.",
    start_url: "./forum.php",
    scope: "./",
    display: "standalone",
    orientation: "portrait-primary",
    background_color: "#0d0f0d",
    theme_color: "#0d0f0d",
    icons: [
      { src: iconDataUri, sizes: "512x512", type: "image/svg+xml", purpose: "any" },
      { src: iconDataUri, sizes: "192x192", type: "image/svg+xml", purpose: "any" },
      { src: iconDataUri, sizes: "512x512", type: "image/svg+xml", purpose: "maskable" }
    ],
    categories: ["social", "productivity"],
    shortcuts: [
      {
        name: "New thread",
        short_name: "New thread",
        description: "Start a new thread on your default board",
        url: "./forum.php?action=board&slug=meta"
      }
    ]
  };

  /* ---------------------------------------------------------------------
     Inject <link rel="manifest"> pointing at a Blob URL
     --------------------------------------------------------------------- */
  function injectManifest() {
    const blob = new Blob([JSON.stringify(manifest)], { type: "application/manifest+json" });
    const url = URL.createObjectURL(blob);

    let link = document.querySelector('link[rel="manifest"]');
    if (!link) {
      link = document.createElement("link");
      link.rel = "manifest";
      document.head.appendChild(link);
    }
    link.href = url;

    // Also set the theme-color meta tag if it's not already present,
    // so the browser chrome matches the manifest's theme_color.
    if (!document.querySelector('meta[name="theme-color"]')) {
      const meta = document.createElement("meta");
      meta.name = "theme-color";
      meta.content = manifest.theme_color;
      document.head.appendChild(meta);
    }

    // Apple falls back to these tags instead of reading the manifest.
    if (!document.querySelector('link[rel="apple-touch-icon"]')) {
      const appleIcon = document.createElement("link");
      appleIcon.rel = "apple-touch-icon";
      appleIcon.href = iconDataUri;
      document.head.appendChild(appleIcon);
    }
    if (!document.querySelector('meta[name="apple-mobile-web-app-capable"]')) {
      const capable = document.createElement("meta");
      capable.name = "apple-mobile-web-app-capable";
      capable.content = "yes";
      document.head.appendChild(capable);
    }
  }

  /* ---------------------------------------------------------------------
     Optional: register a service worker if sw.js exists alongside this
     file. Safe to leave in even without one — registration just fails
     silently (404) if there's nothing to register.
     --------------------------------------------------------------------- */
  function registerServiceWorker() {
    if (!("serviceWorker" in navigator)) return;
    navigator.serviceWorker.register("./sw.js").catch(() => {
      // No service worker yet — that's fine, the manifest still works
      // for "Add to Home Screen" without one.
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      injectManifest();
      registerServiceWorker();
    });
  } else {
    injectManifest();
    registerServiceWorker();
  }
})();
      
