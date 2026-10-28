<style>
    #chatbot-container { position: fixed; bottom: 85px; right: 20px; width: 320px; height: 400px; background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2); display: none; flex-direction: column; z-index: 1050; overflow: hidden; border: 1px solid #ccc; }
    #chatbot-header { background: #212529; color: white; padding: 10px 15px; display: flex; justify-content: space-between; align-items: center; font-weight: bold; }
    #chatbot-messages { flex-grow: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; gap: 10px; background: #f8f9fa; }
    .chat-bubble { max-width: 85%; padding: 8px 12px; border-radius: 15px; font-size: 0.9rem; }
    .bot-msg { background: #e9ecef; align-self: flex-start; color: black; border-bottom-left-radius: 2px; }
    .user-msg { background: #0d6efd; color: white; align-self: flex-end; border-bottom-right-radius: 2px; }
    #chatbot-input-area { display: flex; padding: 10px; border-top: 1px solid #ddd; background: white; }
    #chatbot-input { flex-grow: 1; border: 1px solid #ccc; border-radius: 5px; padding: 5px 10px; outline: none; }
    #chatbot-send { margin-left: 10px; }
    #chatbot-trigger { position: fixed; bottom: 20px; right: 20px; width: 55px; height: 55px; border-radius: 50%; background: #0d6efd; color: white; font-size: 24px; border: none; box-shadow: 0 4px 10px rgba(0,0,0,0.3); cursor: pointer; z-index: 1050; display: flex; justify-content: center; align-items: center; }
</style>

<button id="chatbot-trigger" onclick="toggleChat()">💬</button>

<div id="chatbot-container">
    <div id="chatbot-header">
        <span>Asistente TimeSchedule</span>
        <button type="button" class="btn-close btn-close-white" onclick="toggleChat()" style="font-size: 0.8rem;"></button>
    </div>
    <div id="chatbot-messages">
        <div class="chat-bubble bot-msg">¡Hola! Soy el asistente virtual. ¿En qué te puedo ayudar?</div>
    </div>
    <div id="chatbot-input-area">
        <input type="text" id="chatbot-input" placeholder="Escribe tu duda..." onkeypress="handleEnter(event)">
        <button id="chatbot-send" class="btn btn-primary btn-sm" onclick="enviarMensaje()">Enviar</button>
    </div>
</div>

<script>
    function toggleChat() {
        const chat = document.getElementById('chatbot-container');
        chat.style.display = (chat.style.display === 'flex') ? 'none' : 'flex';
    }

    function handleEnter(e) { if (e.key === 'Enter') enviarMensaje(); }

    function enviarMensaje() {
        const input = document.getElementById('chatbot-input');
        const mensaje = input.value.trim();
        if (!mensaje) return;

        agregarBurbuja(mensaje, 'user-msg');
        input.value = '';

        fetch('chatbot_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mensaje: mensaje })
        })
        .then(res => res.json())
        .then(data => agregarBurbuja(data.respuesta, 'bot-msg'))
        .catch(err => agregarBurbuja("Error de conexión.", 'bot-msg'));
    }

    function agregarBurbuja(texto, clase) {
        const caja = document.getElementById('chatbot-messages');
        const burbuja = document.createElement('div');
        burbuja.className = 'chat-bubble ' + clase;
        burbuja.textContent = texto;
        caja.appendChild(burbuja);
        caja.scrollTop = caja.scrollHeight; // Baja el scroll automáticamente
    }
</script>
