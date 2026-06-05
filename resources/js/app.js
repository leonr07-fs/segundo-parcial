import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';

const app = createApp(App);

app.config.errorHandler = (err, instance, info) => {
    console.error('Error global Vue:', err, info);
    
    // Evitar duplicar el banner de error si ya existe uno en pantalla
    if (document.getElementById('vue-global-error-banner')) {
        return;
    }

    const errorDiv = document.createElement('div');
    errorDiv.id = 'vue-global-error-banner';
    errorDiv.style.position = 'fixed';
    errorDiv.style.top = '10px';
    errorDiv.style.left = '10px';
    errorDiv.style.right = '10px';
    errorDiv.style.backgroundColor = '#f8d7da';
    errorDiv.style.color = '#721c24';
    errorDiv.style.border = '1px solid #f5c6cb';
    errorDiv.style.padding = '15px';
    errorDiv.style.zIndex = '999999';
    errorDiv.style.borderRadius = '8px';
    errorDiv.style.fontFamily = 'monospace';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.whiteSpace = 'pre-wrap';
    errorDiv.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    errorDiv.innerHTML = `<strong>Error detectado en el sistema:</strong><br>${err.stack || err}`;
    
    const closeBtn = document.createElement('button');
    closeBtn.innerText = 'Cerrar Advertencia';
    closeBtn.style.marginTop = '10px';
    closeBtn.style.display = 'block';
    closeBtn.style.padding = '6px 12px';
    closeBtn.style.backgroundColor = '#721c24';
    closeBtn.style.color = 'white';
    closeBtn.style.border = 'none';
    closeBtn.style.borderRadius = '4px';
    closeBtn.style.cursor = 'pointer';
    closeBtn.style.fontWeight = 'bold';
    closeBtn.onclick = () => errorDiv.remove();
    errorDiv.appendChild(closeBtn);
    
    document.body.appendChild(errorDiv);
};

app.mount('#app');

