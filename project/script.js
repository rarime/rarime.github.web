
document.addEventListener('DOMContentLoaded', () => {
    let isAuthenticated = false;
    fetch('api.php?check_auth=1', { credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            if (data.authenticated) {
                isAuthenticated = true;
                document.getElementById('form-title').textContent = 'Обновить данные';
                document.getElementById('username').disabled = true;
                document.getElementById('password').disabled = true;
                document.getElementById('logout').style.display = 'block';
                document.getElementById('username').value = data.user.username;
                document.getElementById('email').value = data.user.email;
                document.getElementById('full_name').value = data.user.full_name;
            }
        });

    const form = document.getElementById('user-form');
    const messageDiv = document.getElementById('message');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = {
            username: document.getElementById('username').value,
            password: document.getElementById('password').value,
            email: document.getElementById('email').value,
            full_name: document.getElementById('full_name').value
        };

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData),
                credentials: 'same-origin'
            });

            const result = await response.json();

            messageDiv.style.display = 'block';
            if (response.ok) {
                messageDiv.className = 'message success';
                if (isAuthenticated) {
                    messageDiv.textContent = 'Данные успешно обновлены!';
                } else {
                    messageDiv.textContent = Регистрация успешна! Логин: ${result.username}, Пароль: ${result.password}, Профиль: ${result.profile_url};
                    form.reset();
                }
            } else {
                messageDiv.className = 'message error';
                messageDiv.textContent = result.error || 'Ошибка при отправке формы';
            }
        } catch (error) {
            messageDiv.style.display = 'block';
            messageDiv.className = 'message error';
            messageDiv.textContent = 'Ошибка сети. Пожалуйста, попробуйте снова.';
            form.submit();
        }
    });
});