(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageServer/index"],
  {
    "04b2": function (n, e, t) {
      "use strict";
      var o = t("37ac");
      t.n(o).a;
    },
    3617: function (n, e, t) {},
    "37ac": function (n, e, t) {},
    5347: function (n, e, t) {
      "use strict";
      (function (n, e) {
        var o = t("47a9");
        t("86d2"), o(t("3240"));
        var r = o(t("c495"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = t), e(r.default);
      }).call(this, t("3223").default, t("df3c").createPage);
    },
    8812: function (n, e, t) {
      "use strict";
      t.d(e, "b", function () {
        return r;
      }),
        t.d(e, "c", function () {
          return i;
        }),
        t.d(e, "a", function () {
          return o;
        });
      var o = {
          confirmModal: function () {
            return t
              .e("components/confirm-modal/confirm-modal")
              .then(t.bind(null, "4e5b"));
          },
        },
        r = function () {
          this.$createElement;
          var n =
              (this._self._c, this.imgsrc("/static/imgs/order-records.png")),
            e = this.imgsrc("/static/imgs/serve-agreement.png");
          this.$mp.data = Object.assign({}, { $root: { m0: n, m1: e } });
        },
        i = [];
    },
    a26d: function (n, e, t) {
      "use strict";
      var o = t("3617");
      t.n(o).a;
    },
    c495: function (n, e, t) {
      "use strict";
      t.r(e);
      var o = t("8812"),
        r = t("fdb2");
      for (var i in r)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            t.d(e, n, function () {
              return r[n];
            });
          })(i);
      t("04b2"), t("a26d");
      var c = t("828b"),
        a = Object(c.a)(
          r.default,
          o.b,
          o.c,
          !1,
          null,
          "eabade96",
          null,
          !1,
          o.a,
          void 0,
        );
      e.default = a.exports;
    },
    fc39: function (n, e, t) {
      "use strict";
      (function (n) {
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var o = {
          data: function () {
            return { infoH: null };
          },
          onLoad: function () {
            var e = this;
            this.$nextTick(function () {
              var t = n.createSelectorQuery().in(e);
              t.select(".info").boundingClientRect(),
                t.exec(function (n) {
                  e.infoH = n[0].height;
                });
            });
          },
          computed: {
            StatusBar: function () {
              return this.$store.state.systemInfo.statusBarHeight;
            },
            CustomBar: function () {
              var e = n.getMenuButtonBoundingClientRect();
              return (
                e.height +
                2 * (e.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                2
              );
            },
          },
          methods: {
            copy: function () {
              this.$refs.confirmModal.show = !0;
            },
            confirmbtn: function () {
              this.$refs.confirmModal.show = !1;
            },
            orderClick: function () {
              n.navigateTo({ url: "/pageServer/order" });
            },
            backPage: function () {
              var e = getCurrentPages(),
                t = e[e.length - 2];
              t && "pages/start/index" == t.route
                ? n.reLaunch({ url: "/pages/shop/shop" })
                : n.navigateBack();
            },
          },
          components: {
            navigation: function () {
              t.e("components/navigation/index")
                .then(
                  function () {
                    return resolve(t("af9e"));
                  }.bind(null, t),
                )
                .catch(t.oe);
            },
            service: function () {
              t.e("pageServer/components/service")
                .then(
                  function () {
                    return resolve(t("9fa9b"));
                  }.bind(null, t),
                )
                .catch(t.oe);
            },
            confirmModal: function () {
              t.e("components/confirm-modal/confirm-modal")
                .then(
                  function () {
                    return resolve(t("4e5b"));
                  }.bind(null, t),
                )
                .catch(t.oe);
            },
            info: function () {
              Promise.all([
                t.e("common/vendor"),
                t.e("pageServer/components/info"),
              ])
                .then(
                  function () {
                    return resolve(t("e6fb"));
                  }.bind(null, t),
                )
                .catch(t.oe);
            },
            serviceBottomLogo: function () {
              t.e("pageServer/components/service-bottom-logo")
                .then(
                  function () {
                    return resolve(t("a3ef"));
                  }.bind(null, t),
                )
                .catch(t.oe);
            },
          },
        };
        e.default = o;
      }).call(this, t("df3c").default);
    },
    fdb2: function (n, e, t) {
      "use strict";
      t.r(e);
      var o = t("fc39"),
        r = t.n(o);
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (n) {
            t.d(e, n, function () {
              return o[n];
            });
          })(i);
      e.default = r.a;
    },
  },
  [["5347", "common/runtime", "common/vendor"]],
]);
