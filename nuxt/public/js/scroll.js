(function(){ /*ページ内リンクのハッシュを削除*/
    window.addEventListener("click" , check_click);

    function check_click(e){
        let target = e.target;
        if(!target || target.tagName !== "A"){return;}
        let href = target.getAttribute("href");
        if(href.indexOf("#") === -1){return;}
        if(href.match(/^[http:|https:|\/\/]/)){return;}
        setTimeout(hash_link_url_adjust , 0);
    }

    function hash_link_url_adjust(href){
        let sp = location.href.split("#");
        history.pushState(null, null, sp[0])
    };
})();