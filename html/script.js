document.addEventListener('DOMContentLoaded', () => {
    const mainCanvas = document.querySelector('.main-canvas');
    const revealText = document.querySelector('.reveal-text');
    const revealItems = document.querySelectorAll('.reveal-item');
    const pCards = document.querySelectorAll('.p-card');
    const navLinks = document.querySelector('.nav-links');
    const heroImg = document.querySelector('.hero-img');

    // 1. Initial State
    mainCanvas.style.opacity = '0';
    mainCanvas.style.transform = 'translateY(50px) scale(0.95)';
    mainCanvas.style.transition = 'all 1.2s cubic-bezier(0.23, 1, 0.32, 1)';

    // 2. Entrance Sequence
    setTimeout(() => {
        // Show Canvas
        mainCanvas.style.opacity = '1';
        mainCanvas.style.transform = 'translateY(0) scale(1)';

        // Reveal Text
        setTimeout(() => {
            revealText.style.opacity = '1';
            revealText.style.transform = 'translateY(0)';
            revealText.style.transition = 'all 0.8s cubic-bezier(0.23, 1, 0.32, 1)';
        }, 400);

        // Reveal Floating Items
        revealItems.forEach((item, index) => {
            setTimeout(() => {
                item.style.opacity = '1';
                item.style.transform = 'scale(1)';
                item.style.transition = 'all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            }, 600 + (index * 200));
        });

        // Reveal Product Cards
        pCards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
                card.style.transition = 'all 0.6s cubic-bezier(0.23, 1, 0.32, 1)';
            }, 800 + (index * 150));
        });

        // Suble Image Zoom
        heroImg.style.transform = 'scale(1.1)';
        heroImg.style.transition = 'transform 10s ease-out';
        setTimeout(() => {
            heroImg.style.transform = 'scale(1)';
        }, 100);

    }, 300);

    // 3. Hover Effects for Cards
    pCards.forEach(card => {
        card.addEventListener('mouseenter', () => {
            if (!card.classList.contains('active')) {
                card.style.transform = 'translateY(-10px) scale(1.05)';
            }
        });
        card.addEventListener('mouseleave', () => {
            if (!card.classList.contains('active')) {
                card.style.transform = 'translateY(0) scale(1)';
            }
        });
        card.addEventListener('click', () => {
            pCards.forEach(c => c.classList.remove('active'));
            card.classList.add('active');
        });
    });

    // 4. Parallax effect on mouse move (Subtle)
    mainCanvas.addEventListener('mousemove', (e) => {
        const xAxis = (window.innerWidth / 2 - e.pageX) / 50;
        const yAxis = (window.innerHeight / 2 - e.pageY) / 50;
        
        // Only apply if viewport is large enough
        if (window.innerWidth > 1024) {
            // Subtle tilt could be added here, but keep it clean as per the reference
        }
    });
});
