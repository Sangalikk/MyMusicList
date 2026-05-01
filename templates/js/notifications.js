// Declaramos as variáveis no topo para que todas as funções do arquivo possam acessá-las
let editModal, modalCloseBtn, editTrackForm, modalTrackIdInput, modalRatingSelect, modalFavoriteCheckbox, modalDeleteBtn;
let modalMainSection, modalConfirmSection, btnConfirmDeleteFinal, btnCancelDelete;

document.addEventListener('DOMContentLoaded', () => {
    // Inicializamos os elementos quando o DOM estiver pronto
    editModal = document.getElementById('track-edit-modal');
    modalCloseBtn = editModal.querySelector('.modal-close-btn');
    editTrackForm = document.getElementById('edit-track-form');
    modalTrackIdInput = document.getElementById('modal-track-id');
    modalRatingSelect = document.getElementById('modal-rating');
    modalFavoriteCheckbox = document.getElementById('modal-favorite');
    modalDeleteBtn = document.getElementById('modal-delete-btn');
    modalMainSection = document.getElementById('modal-main-section');
    modalConfirmSection = document.getElementById('modal-confirm-section');
    btnConfirmDeleteFinal = document.getElementById('btn-confirm-delete-final');
    btnCancelDelete = document.getElementById('btn-cancel-delete');

    // Abre o modal quando o badge de nota é clicado
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('rating-badge-clickable')) {
            e.preventDefault();
            const trackCard = e.target.closest('.track-card');
            const trackData = {
                internal_track_id: e.target.dataset.internalTrackId,
                rating: e.target.dataset.rating,
                is_favorite: e.target.dataset.isFavorite === '1'
            };
            openEditModal(trackData, trackCard);
        }
    });

    // Fecha o modal
    modalCloseBtn.addEventListener('click', closeModal);
    editModal.addEventListener('click', (e) => {
        if (e.target === editModal) { // Fecha apenas se clicar no overlay
            closeModal();
        }
    });

    // Lida com o envio do formulário do modal (Atualizar)
    editTrackForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(editTrackForm);
        formData.set('action', 'update'); // Garante que a ação é 'update'

        const submitBtn = editTrackForm.querySelector('.btn-save');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Salvando...';
        submitBtn.disabled = true;

        try {
            const response = await fetch('updateUserTrack.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            showNotification(result.message, result.status === 'success');
            if (result.status === 'success') {
                closeModal();
                // Recarrega a página para refletir as mudanças na UI (nota, favorito)
                location.reload(); 
            }
        } catch (error) {
            showNotification('Erro ao salvar alterações.', false);
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    });

    // Intercepta cliques no botão de "Ouvir Prévia"
    document.addEventListener('click', async (e) => {
        if (e.target.classList.contains('btn-listen-trigger')) {
            const container = e.target.closest('.preview-wrapper');
            const trackId = e.target.dataset.trackId;
            
            // 1. Preparar dados para o PHP (usamos os inputs ocultos que já estão no card)
            const card = e.target.closest('.track-card');
            const formData = new FormData(card.querySelector('.search-actions'));
            formData.set('action', 'listen'); // Sobrescreve a ação para "listen"

            // 2. Avisar o servidor que alguém clicou para ouvir
            try {
                fetch('paginaBuscar.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
            } catch (err) { console.error("Erro ao registrar ouvinte"); }

            // 3. Trocar o botão pelo Iframe da Deezer
            container.innerHTML = `
                <iframe title="deezer-widget" 
                    src="https://widget.deezer.com/widget/dark/track/${trackId}" 
                    width="100%" height="80" frameborder="0" allowtransparency="true" 
                    allow="encrypted-media; clipboard-write" 
                    style="margin-top: 10px; border-radius: 8px;"></iframe>
            `;
        }
    });

    // Alterna para a tela de confirmação de exclusão
    modalDeleteBtn.addEventListener('click', () => {
        modalMainSection.style.display = 'none';
        modalConfirmSection.style.display = 'block';
    });

    // Cancela a remoção e volta para a edição
    btnCancelDelete.addEventListener('click', () => {
        modalConfirmSection.style.display = 'none';
        modalMainSection.style.display = 'block';
    });

    // Executa a remoção definitiva via AJAX
    btnConfirmDeleteFinal.addEventListener('click', async () => {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('track_id', modalTrackIdInput.value);

        btnConfirmDeleteFinal.textContent = 'Removendo...';
        btnConfirmDeleteFinal.disabled = true;

        try {
            const response = await fetch('updateUserTrack.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            showNotification(result.message, result.status === 'success');
            if (result.status === 'success') {
                closeModal();
                const trackCardToRemove = document.querySelector(`.track-card[data-internal-track-id="${modalTrackIdInput.value}"]`);
                if (trackCardToRemove) trackCardToRemove.remove();
            }
        } catch (error) {
            showNotification('Erro ao remover música.', false);
        } finally {
            btnConfirmDeleteFinal.textContent = 'Sim, Remover';
            btnConfirmDeleteFinal.disabled = false;
        }
    });

    // Intercepta todos os formulários de adição de música
    document.addEventListener('submit', async (e) => {
        if (e.target.classList.contains('search-actions')) {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('.btn-save');
            
            // Feedback visual no botão
            const originalText = submitBtn.textContent;
            submitBtn.textContent = '...';
            submitBtn.disabled = true;

            try {
                const response = await fetch('paginaBuscar.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();
                showNotification(result.message, result.status === 'success');
            } catch (error) {
                showNotification('Erro de conexão com o servidor', false);
            } finally {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        }
    });
});

function showNotification(text, isSuccess) {
    const container = document.getElementById('notification-container');
    const notification = document.createElement('div');
    
    notification.className = `notification ${isSuccess ? 'success' : 'error'}`;
    notification.innerHTML = `<span>${isSuccess ? '✓' : '✕'}</span> ${text}`;

    container.appendChild(notification);

    // Remove o pop-up após 3 segundos
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 400);
    }, 3000);
}

function openEditModal(trackData, trackCardElement) {
    // Armazena a referência do elemento do card para futuras atualizações de UI
    // (Embora por enquanto estejamos recarregando a página após update)
    editModal.dataset.currentTrackCardId = trackCardElement.dataset.internalTrackId; 
    
    modalTrackIdInput.value = trackData.internal_track_id;
    modalRatingSelect.value = trackData.rating === 'S/N' ? '' : trackData.rating; // Lida com 'S/N'
    modalFavoriteCheckbox.checked = trackData.is_favorite;
    editModal.classList.add('active');
}

function closeModal() {
    editModal.classList.remove('active');
    editTrackForm.reset(); // Limpa o formulário ao fechar
    modalMainSection.style.display = 'block';
    modalConfirmSection.style.display = 'none';
}