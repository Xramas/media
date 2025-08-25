// 当整个页面的HTML结构加载完成后执行
document.addEventListener('DOMContentLoaded', () => {

    // --- 视频卡片淡入效果 ---
    const videoCards = document.querySelectorAll('.video-card');

    videoCards.forEach(card => {
        const image = card.querySelector('img');

        // 定义一个函数，用于移除loading状态，让卡片显示
        const showCard = () => {
            card.classList.remove('loading');
        };

        if (image) {
            // 如果图片已经加载完成（例如从缓存加载），则立即显示
            if (image.complete) {
                showCard();
            } else {
                // !! 关键改动在这里 !!
                // 无论图片加载成功 (load) 还是失败 (error)，
                // 我们都调用 showCard 函数，让卡片显示出来。
                image.addEventListener('load', showCard);
                image.addEventListener('error', showCard);
            }
        } else {
            // 如果卡片根本没有图片，也直接显示
            showCard();
        }
    });

});
