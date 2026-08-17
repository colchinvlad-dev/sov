(function() {
    'use strict';

    class LikeWidget {
        constructor(button) {
            if (!button) {
                console.warn('Кнопка лайка не найдена');
                return;
            }

            this.button = button;
            this.countElement = this.button.querySelector('.count');
            this.labelElement = this.button.querySelector('.label');
            this.heartElement = this.button.querySelector('.heart');
            
            this.elementId = this.button.dataset.elementId;
            this.isLiked = this.button.dataset.isLiked === 'true';
            this.url = this.button.dataset.url || '/like-handler.php';

            if (!this.elementId) {
                console.warn('Не указан data-element-id');
                return;
            }

            this.initialCount = parseInt(this.countElement.textContent, 10) || 0;
            this.bindEvents();
        }

        bindEvents() {
            this.button.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleClick();
            });
        }

        async handleClick() {
            if (this.button.disabled) return;

            this.button.disabled = true;
            this.button.classList.add('loading');

            const action = this.isLiked ? 'unlike' : 'like';
            const previousState = {
                isLiked: this.isLiked,
                count: this.initialCount,
                label: this.labelElement ? this.labelElement.textContent : ''
            };

            try {
                const formData = new FormData();
                formData.append('id', this.elementId);
                formData.append('action', action);

                const response = await fetch(this.url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ошибка: ${response.status}`);
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Ошибка сервера');
                }

                this.updateUI(data);

            } catch (error) {
                console.error('Ошибка лайка:', error);
                
                // Откат
                this.countElement.textContent = previousState.count;
                if (this.labelElement) {
                    this.labelElement.textContent = previousState.label;
                }
                this.isLiked = previousState.isLiked;

                if (this.isLiked) {
                    this.button.classList.add('liked');
                } else {
                    this.button.classList.remove('liked');
                }

                // Показываем ошибку (alert, можно заменить на тостер)
                alert('Error ' + error.message);

            } finally {
                this.button.disabled = false;
                this.button.classList.remove('loading');
            }
        }

        updateUI(data) {
            this.countElement.textContent = data.new_count;
            this.initialCount = data.new_count;
            this.isLiked = data.is_liked;

            if (this.isLiked) {
                this.button.classList.add('liked');
                if (this.labelElement) {
                    this.labelElement.textContent = 'Снять лайк';
                }
            } else {
                this.button.classList.remove('liked');
                if (this.labelElement) {
                    this.labelElement.textContent = 'Лайк';
                }
            }

            this.button.dataset.isLiked = this.isLiked ? 'true' : 'false';

            // Анимация сердечка
            if (this.isLiked && this.heartElement) {
                this.heartElement.style.animation = 'none';
                setTimeout(() => {
                    this.heartElement.style.animation = 'likePop 0.4s ease';
                }, 10);
            }
        }
    }

    // Инициализация
    document.addEventListener('DOMContentLoaded', function() {
        const button = document.querySelector('.like-btn');
        if (button) {
            new LikeWidget(button);
        }
    });

})();