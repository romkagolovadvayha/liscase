(function($){
    var Renderer = function(canvas)
    {
        var dom = $(canvas).parent();
        console.log(dom);
        var canvas = $(canvas).get(0);
        var ctx = canvas.getContext("2d");
        var particleSystem;
        var gfx = arbor.Graphics(canvas);
        var _vignette = null;

        var that = {
            init:function(system){
                //начальная инициализация
                particleSystem = system;
                particleSystem.screen({
                    size: {
                        width: dom.width(),
                        height: dom.height()
                    }
                });
                // particleSystem.screenSize(dom.width, dom.height);
                // particleSystem.screenSize(dom.width(), dom.height());

                // particleSystem.screenPadding(80);

                $(window).resize(that.resize);
                that.resize();
                that.initMouseHandling();
            },
            resize:function(){
                canvas.width = dom.width(),
                canvas.height = dom.height()
                sys.screen({size:{width:canvas.width, height:canvas.height}})
                _vignette = null
                that.redraw()
            },
            redraw:function(){
                gfx.clear()
                sys.eachEdge(function(edge, p1, p2){
                    if (edge.source.data.alpha * edge.target.data.alpha == 0) return
                    gfx.line(p1, p2, {stroke:"#b2b19d", width:2, alpha:edge.target.data.alpha})
                })
                sys.eachNode(function(node, pt){
                    var w = Math.max(10, 10 );
                    if (node.data.alpha===0) return;
                    if (node.data.shape=='dot') {
                        gfx.oval(pt.x-w/2, pt.y-w/2, w, w, {fill:"#000", alpha:node.data.alpha});
                        gfx.text(node.name, pt.x, pt.y+7, {color:"white", align:"center", font:"Arial", size:12});
                        // gfx.text(node.name, pt.x, pt.y+7, {color:"white", align:"center", font:"Arial", size:12})
                    } else {
                        gfx.rect(pt.x-w/2, pt.y-8, w, 10, 5, {fill:"#b2b19d", alpha:node.data.alpha})
                        gfx.text(node.name, pt.x, pt.y+9, {color:"white", align:"center", font:"Arial", size:12})
                        // gfx.text(node.name, pt.x, pt.y+9, {color:"white", align:"center", font:"Arial", size:12})
                    }
                });
            },

            initMouseHandling:function() { //события с мышью
                var dragged = null;   //вершина которую перемещают
                var handler = {
                    clicked:function(e){ //нажали
                        var pos = $(canvas).offset(); //получаем позицию canvas
                        _mouseP = arbor.Point(e.pageX-pos.left, e.pageY-pos.top); //и позицию нажатия кнопки относительно canvas
                        dragged = particleSystem.nearest(_mouseP); //определяем ближайшую вершину к нажатию
                        if (dragged && dragged.node !== null){
                            dragged.node.fixed = true; //фиксируем её
                        }
                        $(canvas).bind('mousemove', handler.dragged); //слушаем события перемещения мыши
                        $(window).bind('mouseup', handler.dropped);  //и отпускания кнопки
                        return false;
                    },
                    dragged:function(e){ //перетаскиваем вершину
                        var pos = $(canvas).offset();
                        var s = arbor.Point(e.pageX-pos.left, e.pageY-pos.top);

                        if (dragged && dragged.node !== null){
                            var p = particleSystem.fromScreen(s);
                            dragged.node.p = p; //тянем вершину за нажатой мышью
                        }

                        return false;
                    },
                    dropped:function(e){ //отпустили
                        if (dragged===null || dragged.node===undefined) return; //если не перемещали, то уходим
                        if (dragged.node !== null) dragged.node.fixed = false; //если перемещали - отпускаем
                        dragged = null; //очищаем
                        $(canvas).unbind('mousemove', handler.dragged); //перестаём слушать события
                        $(window).unbind('mouseup', handler.dropped);
                        _mouseP = null;
                        return false;
                    }
                }
                // слушаем события нажатия мыши
                $(canvas).mousedown(handler.clicked);
            },

        }
        return that;
    }

    $(document).ready(function(){
        sys = arbor.ParticleSystem(1000); // создаём систему
        sys.parameters({gravity:true}); // гравитация вкл
        sys.renderer = Renderer("#viewport")

        $.getJSON("/servers/teams-data?server=nolimit",
            function(data){
                $.each(data.nodes, function(i,node){
                    sys.addNode(node.name); //добавляем вершину
                });
                $.each(data.edges, function(i,edge){
                    sys.addEdge(sys.getNode(edge.src),sys.getNode(edge.dest));
                });
            });

    })

})(this.jQuery)