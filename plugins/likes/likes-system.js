document.addEventListener('DOMContentLoaded', function() {
    
    function getNotificationMessage(action, voteType) {
        const voteText = voteType === 'like' ? 'like' : 'dislike';
        
        switch (action) {
            case 'added':
                return 'Your ' + voteText + ' has been added!';
            case 'updated':
                return 'Your vote has been updated!';
            case 'removed':
                return 'Your vote has been removed!';
            default:
                return 'Done!';
        }
    }
    
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `likes-notification ${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Show with animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        // Hide adter 3 sec
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
    
    function sendVoteRequest(postId, voteType, pageUrl, button, container) {
        const formData = new FormData();
        formData.append('action', 'vote_article');
        formData.append('post_id', postId);
        formData.append('vote_type', voteType);
        formData.append('page_url', pageUrl);
        formData.append('nonce', likes_ajax.nonce);
        
        fetch(likes_ajax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const responseData = data.data;
                
                // Update Counts
                const likeCount = container.querySelector('.like-count');
                const dislikeCount = container.querySelector('.dislike-count');
                const likesSum = container.querySelector('.likes-sum');
                
                if (likeCount) likeCount.textContent = responseData.likes_count;
                if (dislikeCount) dislikeCount.textContent = responseData.dislikes_count;
                const likeSumCount = responseData.likes_count - responseData.dislikes_count;
                likesSum.textContent = (likesSum && likeSumCount > 0) ? likeSumCount : 0;

                
                // Update buttons
                const likeBtn = container.querySelector('.like-btn');
                const dislikeBtn = container.querySelector('.dislike-btn');
                
                likeBtn.classList.remove('active');
                dislikeBtn.classList.remove('active');
                
                if (responseData.user_vote === 'like') {
                    likeBtn.classList.add('active');
                } else if (responseData.user_vote === 'dislike') {
                    dislikeBtn.classList.add('active');
                }
                
                showNotification(getNotificationMessage(responseData.action, voteType));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error', 'error');
        })
        .finally(() => {
            const buttons = container.querySelectorAll('.like-btn, .dislike-btn');
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.classList.remove('loading');
            });
        });
    }
    
    // Deligate handlres
    document.addEventListener('click', function(e) {
        const button = e.target.closest('.like-btn, .dislike-btn');
        if (!button) return;
        
        e.preventDefault();
        
        const container = button.closest('.likes-container');
        if (!container) return;
        
        const postId = container.dataset.postId;
        const voteType = button.dataset.vote;
        const pageUrl = window.location.href;
        
        const buttons = container.querySelectorAll('.like-btn, .dislike-btn');
        buttons.forEach(btn => btn.disabled = true);
        
        button.classList.add('loading');
        
        sendVoteRequest(postId, voteType, pageUrl, button, container);
    });
    
    document.addEventListener('mouseenter', function(e) {
        if (e.target && e.target.nodeType === 1) {
            const button = e.target.closest('.like-btn, .dislike-btn');
            if (button) {
                button.classList.add('hover');
            }    
        }
    }, true);
    
    document.addEventListener('mouseleave', function(e) {
        if (e.target && e.target.nodeType === 1) {
            const button = e.target.closest('.like-btn, .dislike-btn');
            if (button) {
                button.classList.remove('hover');
            }    
        }
    }, true);
    
    document.addEventListener('mousedown', function(e) {
        const button = e.target.closest('.like-btn, .dislike-btn');
        if (button && !button.disabled) {
            button.style.transform = 'scale(0.95)';
        }
    });
    
    document.addEventListener('mouseup', function(e) {
        const button = e.target.closest('.like-btn, .dislike-btn');
        if (button) {
            button.style.transform = '';
        }
    });
    
    // Optional. Accesibility. 
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            const button = e.target.closest('.like-btn, .dislike-btn');
            if (button) {
                e.preventDefault();
                button.click();
            }
        }
    });

});