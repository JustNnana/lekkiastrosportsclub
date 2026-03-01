// Register service worker
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(registration => {
        console.log('Service Worker registered successfully with scope:', registration.scope);
      })
      .catch(error => {
        console.error('Service Worker registration failed:', error);
      });
  });
} else {
  console.warn('Service workers are not supported by this browser');
}

// Service worker - handle installation banner
let deferredPrompt;
const installButton = document.getElementById('pwa-install-btn');

if (installButton) {
  // Initially hide the install button
  installButton.style.display = 'none';
  
  // Show button when we can install
  window.addEventListener('beforeinstallprompt', (e) => {
    console.log('beforeinstallprompt fired');
    // Prevent the default browser install prompt
    e.preventDefault();
    
    // Store the event for later
    deferredPrompt = e;
    
    // Make the button visible
    installButton.style.display = 'block';
  });
  
  // Handle install button click
  installButton.addEventListener('click', async (e) => {
    e.preventDefault();
    console.log('Install button clicked');
    
    if (!deferredPrompt) {
      console.log('No installation prompt available');
      return;
    }
    
    // Show the prompt
    deferredPrompt.prompt();
    
    // Wait for the user's choice
    const { outcome } = await deferredPrompt.userChoice;
    console.log(`User response to the install prompt: ${outcome}`);
    
    // We no longer need the prompt
    deferredPrompt = null;
    
    // Hide the button if user accepted
    if (outcome === 'accepted') {
      installButton.style.display = 'none';
    }
  });
}

// Handle successful installation
window.addEventListener('appinstalled', (e) => {
  console.log('App was installed');
  
  // Hide the install button
  if (installButton) {
    installButton.style.display = 'none';
  }
});