!function($){
    "use strict";

    Codevz_Plus.animated_text = function() {
        var $headlines = $(".cz_headline");
        if(!$headlines.length) return;

        $headlines.each(function(){
            var $headline = $(this),
                duration = $headline.data("time") || 3000,
                letterDelay = 50,
                letterAnimDelay = 150,
                typeDelay = 500,
                nextDelay = 800,
                clipDuration = 600;

            var $words = $headline.find(".cz_words-wrapper b");
            if(!$words.length) return;

            // Prepare letters for 'letters' and 'type' models
            if ($headline.hasClass("letters") || $headline.hasClass("type")) {
                $words.each(function(){
                    var $word = $(this),
                        letters = $word.text().split(""),
                        visible = $word.hasClass("is-visible");
                    var html = letters.map(l => `<i class="${visible ? 'in' : ''}">${l}</i>`).join("");
                    $word.html(html).css("opacity", 1);
                });
            }

            // Start the animation
            setTimeout(() => switchWord($headline.find(".is-visible").eq(0)), duration);

            /** Core function: Switch between words **/
            function switchWord($word){
                var $next = getNext($word);

                // ---- TYPE MODEL ----
                if($headline.hasClass("type")){
                    var $wrapper = $headline.find(".cz_words-wrapper");

                    // Start deleting current word
                    $wrapper.addClass("selected").removeClass("waiting");
                    setTimeout(() => {
                        $wrapper.removeClass("selected");
                        deleteLetters($word.find("i").last(), () => {
                            $word.removeClass("is-visible").addClass("is-hidden");
                            $next.removeClass("is-hidden").addClass("is-visible");

                            // Start typing the next word
                            typeLetters($next.find("i").eq(0), () => {
                                // Pause before next cycle
                                setTimeout(() => switchWord($next), duration);
                            });
                        });
                    }, typeDelay);

                // ---- LETTERS MODEL ----
                } else if($headline.hasClass("letters")){
                    var longer = $word.children("i").length >= $next.children("i").length;
                    hideLetters($word.find("i").eq(0), $word, longer, letterDelay, () => {
                        showLetters($next.find("i").eq(0), $next, longer, letterDelay, () => {
                            setTimeout(() => switchWord($next), duration);
                        });
                    });

                // ---- CLIP MODEL ----
                } else if($headline.hasClass("clip")){
                    $headline.find(".cz_words-wrapper").animate({ width: "2px" }, clipDuration, function(){
                        changeWord($word, $next);
                        showWord($next);
                    });

                // ---- LOADING BAR MODEL ----
                } else if($headline.hasClass("loading-bar")){
                    var $wrapper = $headline.find(".cz_words-wrapper");
                    $wrapper.removeClass("is-loading");
                    changeWord($word, $next);
                    setTimeout(() => switchWord($next), duration);
                    setTimeout(() => $wrapper.addClass("is-loading"), duration - 2000);

                // ---- SIMPLE ROTATION ----
                } else {
                    changeWord($word, $next);
                    setTimeout(() => switchWord($next), duration);
                }
            }

            /** Typewriter: delete letters one by one **/
            function deleteLetters($letter, done){
                if(!$letter.length) return done();
                $letter.removeClass("in").addClass("out");
                setTimeout(() => {
                    deleteLetters($letter.prev(), done);
                }, letterDelay);
            }

            /** Typewriter: add letters one by one **/
            function typeLetters($letter, done){
                if(!$letter.length) return done();
                $letter.addClass("in").removeClass("out");
                setTimeout(() => {
                    typeLetters($letter.next(), done);
                }, letterDelay);
            }

            /** Letters animation **/
            function hideLetters($letter, $word, longer, delay, callback){
                $letter.removeClass("in").addClass("out");
                if($letter.is(":last-child")) return callback && callback();
                setTimeout(() => hideLetters($letter.next(), $word, longer, delay, callback), delay);
            }

            function showLetters($letter, $word, longer, delay, callback){
                $letter.addClass("in").removeClass("out");
                if($letter.is(":last-child")) return callback && callback();
                setTimeout(() => showLetters($letter.next(), $word, longer, delay, callback), delay);
            }

            /** Simple switch helpers **/
            function changeWord($old, $new){
                $old.removeClass("is-visible").addClass("is-hidden");
                $new.removeClass("is-hidden").addClass("is-visible");
            }

            function showWord($word){
                if($headline.hasClass("clip")){
                    $headline.find(".cz_words-wrapper").animate({ width: $word.width() }, clipDuration, () => {
                        setTimeout(() => switchWord($word), duration);
                    });
                }
            }

            function getNext($word){
                return $word.is(":last-child") ? $word.parent().children().eq(0) : $word.next();
            }
        });
    };

    Codevz_Plus.animated_text();

}(jQuery);
