/**
 * BANNER SLIDER CHUYEN DONG TU DONG & DIEU KHIEN THU CONG
 * He thong Tinh Nguyen D2L Team
 */
document.addEventListener('DOMContentLoaded', function () {
    const slides = document.querySelectorAll('.slide_banner');
    const dots = document.querySelectorAll('.cham_chuyen_slide');
    const prevBtn = document.querySelector('.nut_chuyen_trai');
    const nextBtn = document.querySelector('.nut_chuyen_phai');
    const sliderContainer = document.querySelector('.banner_hero_slider');

    if (!slides.length) return;

    let currentSlide = 0;
    let slideInterval = null;

    function showSlide(index) {
        slides.forEach((slide, i) => {
            slide.classList.toggle('active', i === index);
        });
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === index);
        });
        currentSlide = index;
    }

    function nextSlide() {
        let nextIndex = (currentSlide + 1) % slides.length;
        showSlide(nextIndex);
    }

    function prevSlide() {
        let prevIndex = (currentSlide - 1 + slides.length) % slides.length;
        showSlide(prevIndex);
    }

    function startAutoPlay() {
        stopAutoPlay();
        slideInterval = setInterval(nextSlide, 5000); // Tu dong chuyen sau 5 giay
    }

    function stopAutoPlay() {
        if (slideInterval) {
            clearInterval(slideInterval);
            slideInterval = null;
        }
    }

    // Gan su kien nut bam
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            nextSlide();
            startAutoPlay();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            prevSlide();
            startAutoPlay();
        });
    }

    dots.forEach((dot, index) => {
        dot.addEventListener('click', function () {
            showSlide(index);
            startAutoPlay();
        });
    });

    // Tam dung khi re chuot vao banner
    if (sliderContainer) {
        sliderContainer.addEventListener('mouseenter', stopAutoPlay);
        sliderContainer.addEventListener('mouseleave', startAutoPlay);
    }

    // Khoi dong tu dong
    startAutoPlay();
});
