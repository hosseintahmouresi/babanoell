const mpLottieElementAnimationHandle = function (params){
    let lottieContainer = params.container;
    let lottieAnimation = bodymovin.loadAnimation({
        container: lottieContainer,
        path: params.path,
        renderer: params.renderer,
        loop: params.loop,
        autoplay: params.autoplay,
        lazyload: params.lazyload,
    });

    lottieAnimation.setSpeed(params.playSpeed || 1);
}

let mpLottieContainers = document.querySelectorAll('.mp-lottie-file-wrap');

if (mpLottieContainers){
    mpLottieContainers.forEach(function (element){
        let lottieSrc = element.dataset.src;
        if (!lottieSrc)
            return false;

        mpLottieElementAnimationHandle({
            container: document.getElementById(element.id),
            path: lottieSrc,
            renderer: 'svg',
            loop: true,
            autoplay: true,
            lazyload: true
        });

        element.dataset.src = '';
    })
}