document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('tutorForm').addEventListener('submit', async function(event) {
        event.preventDefault();

        const question = document.getElementById('question').value;
        const learningLevel = document.getElementById('learningLevel').value;
        const contextFile = document.getElementById('contextFile').files[0];
        const answerSection = document.getElementById('answerSection');
        const answerDiv = document.getElementById('answer');

        if (!question.trim()) {
            showModal('Input Error', 'Please enter a question.');
            return;
        }

        answerSection.classList.remove('hidden');
        answerDiv.innerHTML = '<div class="loading-spinner"></div>';

        const formData = new FormData();
        formData.append('question', question);
        formData.append('learningLevel', learningLevel);
        if (contextFile) {
            formData.append('contextFile', contextFile);
        }

        try {
            const response = await fetch('server.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! Status: ${response.status} - ${errorText}`);
            }

            const result = await response.json();

            if (result.success) {
                answerDiv.innerHTML = result.answer;
            } else {
                showModal('API Error', result.error);
                answerSection.classList.add('hidden');
            }

        } catch (error) {
            console.error('Fetch error:', error);
            showModal('Request Error', 'Failed to get a response. Please check your network connection and the server logs.');
            answerSection.classList.add('hidden');
        }
    });

    function showModal(title, message) {
        const modal = document.getElementById('errorModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');

        modalTitle.textContent = title;
        modalBody.innerHTML = `<p>${message}</p>`;
        modal.classList.remove('hidden');
    }

    document.querySelector('.modal-close').addEventListener('click', () => {
        document.getElementById('errorModal').classList.add('hidden');
    });

    window.onclick = function(event) {
        const modal = document.getElementById('errorModal');
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    }
});
