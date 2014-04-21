$(function () {
    updateTemps();
    setInterval(function () {
        updateTemps();
    }, 60000);
});

function updateTemps() {
    $.getJSON('temp.json', function (data) {
        $.each(data, function (key, val) {
            var friendlyName = key.replace(/ /g, '');

            var obj = $("#template").clone();

            obj.find('.tab-pane').each(function () {
                var img = $("<img>");
                img.attr('src', 'graphs/' + key + '_' + $(this).attr('id') + '.png');
                $(this).html(img);
            })

            obj.find("*[id]").andSelf().each(function () {
                $(this).attr('id', function (i, id) {
                    return id + "_" + friendlyName;
                });
            });

            $("temperature-title", obj).html(val.name);
            $("temp", obj).html(val.temp);
            $("footer > p", obj).attr('data-livestamp', val.updated).attr('title', 'Last Updated at ' + moment.unix(val.updated).format());

            obj.find('.nav > li a').each(function () {
                $(this).attr('href', function (i, id) {
                    return id + "_" + friendlyName
                });
            });

            obj.attr('id', friendlyName);

            var colour = 'none';

            if (val.min != null) {
                obj.attr('data-min', val.min);
                if (val.temp <= val.min) {
                    colour = 'primary';
                }
            }

            if (val.max != null) {
                obj.attr('data-max', val.max);
                if (val.temp >= val.max) {
                    colour = 'danger';
                }
            }

            if (val.min != null && val.max != null) {
                if (val.min <= val.temp && val.temp <= val.max) {
                    colour = 'success';
                }
            }

            $(".panel", obj).removeClass('panel-primary').removeClass('panel-danger').removeClass('panel-success').addClass('panel-' + colour);


            var li = $("<li>");
            var a = $("<a>");
            a.attr('href', '#' + friendlyName);
            a.html(val.name);
            li.html(a);

            if ($('#' + friendlyName).length > 0) {
                $("#" + friendlyName).html(obj.html());
            } else {
                $("#navbar-example2").find(".nav").append(li);
                obj.show();
                $("#content").append(obj);
            }
        });

        $('[data-spy="scroll"]').each(function () {
            var $spy = $(this).scrollspy('refresh')
        });
    });
}