var QuickSidebar = function() {
    var i = function() {
            $(".dropdown-quick-sidebar-toggler a, .page-quick-sidebar-toggler, .quick-sidebar-toggler").click(function(i) {
                $("body").toggleClass("page-quick-sidebar-open")
            })
        },
        a = function() {
            var i = $(".page-quick-sidebar-wrapper"),
                a = i.find(".page-quick-sidebar-chat"),
                e = function() {
                    var e, t = i.find(".page-quick-sidebar-chat-users");
                    e = i.height() - i.find(".nav-tabs").outerHeight(!0), App.destroySlimScroll(t), t.attr("data-height", e-48), App.initSlimScroll(t);
                    var r = a.find(".page-quick-sidebar-chat-user-messages"),
                        s = e - a.find(".page-quick-sidebar-chat-user-form").outerHeight(!0);
                    s -= a.find(".page-quick-sidebar-nav").outerHeight(!0), App.destroySlimScroll(r), r.attr("data-height", s), App.initSlimScroll(r)
                };
            e(), App.addResizeHandler(e), i.find(".page-quick-sidebar-chat-users .media-list > .media").click(function() {
                a.addClass("page-quick-sidebar-content-item-shown")
            }), i.find(".page-quick-sidebar-chat-user .page-quick-sidebar-back-to-list").click(function() {
                a.removeClass("page-quick-sidebar-content-item-shown")
            });
            var t = function(i) {
                i.preventDefault();

            };
            a.find(".page-quick-sidebar-chat-user-form .btn").click(t), a.find(".page-quick-sidebar-chat-user-form .form-control").keypress(function(i) {
                if (13 == i.which) return t(i), !1
            })
        },
        e = function() {
            var i = $(".page-quick-sidebar-wrapper"),
                a = function() {
                    var a, e = i.find(".page-quick-sidebar-alerts-list");
                    a = i.height() - 80 - (i.find(".nav-justified > .nav-tabs").outerHeight()), App.destroySlimScroll(e), e.attr("data-height", a), App.initSlimScroll(e)
                };
            a(), App.addResizeHandler(a)
        },
        t = function() {
            var i = $(".page-quick-sidebar-wrapper"),
                a = function() {
                    var a, e = i.find(".page-quick-sidebar-settings-list");
                    a = i.height() - 80 - (i.find(".nav-justified > .nav-tabs").outerHeight()), App.destroySlimScroll(e), e.attr("data-height", a), App.initSlimScroll(e)
                };
            a(), App.addResizeHandler(a)
        };
    return {
        init: function() {
            i(), a(), e(), t()
        }
    }
}();
App.isAngularJsApp() === !1 && jQuery(document).ready(function() {
    QuickSidebar.init()
});