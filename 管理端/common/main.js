(global.webpackJsonp = global.webpackJsonp || []).push([
  ["common/main"],
  {
    "582c": function (e, t, n) {
      "use strict";
      Object.defineProperty(t, "__esModule", { value: !0 }),
        (t.default = void 0),
        (t.default = {
          onLaunch: function () {
            this.$store.dispatch("getSystemInfo"),
              this.$store.dispatch("getDicto");
          },
          onLoad: function () {},
          onShow: function () {},
          onHide: function () {},
          onPageNotFound: function () {},
        });
    },
    "6b90": function (e, t, n) {
      "use strict";
      var o = n("fb83");
      n.n(o).a;
    },
    "77c7": function (e, t, n) {},
    bd6e: function (e, t, n) {
      "use strict";
      n.r(t);
      var o = n("cc8d");
      for (var r in o)
        ["default"].indexOf(r) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return o[e];
            });
          })(r);
      n("faf9"), n("6b90");
      var u = n("828b"),
        a = Object(u.a)(
          o.default,
          void 0,
          void 0,
          !1,
          null,
          null,
          null,
          !1,
          void 0,
          void 0,
        );
      t.default = a.exports;
    },
    cb60: function (e, t, n) {
      "use strict";
      (function (e, t, o) {
        var r = n("47a9"),
          u = n("3b2d"),
          a = r(n("7ca3"));
        n("86d2");
        var c = r(n("3240")),
          f = r(n("bd6e")),
          i = r(n("4f4d")),
          l = r(n("3f77")),
          d = (function (e, t) {
            if (e && e.__esModule) return e;
            if (null === e || ("object" !== u(e) && "function" != typeof e))
              return { default: e };
            var n = (function (e) {
              if ("function" != typeof WeakMap) return null;
              var t = new WeakMap(),
                n = new WeakMap();
              return (function (e) {
                return e ? n : t;
              })(e);
            })(t);
            if (n && n.has(e)) return n.get(e);
            var o = {},
              r = Object.defineProperty && Object.getOwnPropertyDescriptor;
            for (var a in e)
              if (
                "default" !== a &&
                Object.prototype.hasOwnProperty.call(e, a)
              ) {
                var c = r ? Object.getOwnPropertyDescriptor(e, a) : null;
                c && (c.get || c.set)
                  ? Object.defineProperty(o, a, c)
                  : (o[a] = e[a]);
              }
            return (o.default = e), n && n.set(e, o), o;
          })(n("702f")),
          s = (r(n("7502")), r(n("5881"))),
          p = n("0126"),
          b = r(n("8869"));
        function g(e, t) {
          var n = Object.keys(e);
          if (Object.getOwnPropertySymbols) {
            var o = Object.getOwnPropertySymbols(e);
            t &&
              (o = o.filter(function (t) {
                return Object.getOwnPropertyDescriptor(e, t).enumerable;
              })),
              n.push.apply(n, o);
          }
          return n;
        }
        (e.__webpack_require_UNI_MP_PLUGIN__ = n),
          (c.default.config.productionTip = !1),
          (f.default.mpType = "app"),
          c.default.use(
            s.default,
            {
              Token: {
                name: "Token",
                watchKey: "token",
                deep: !0,
                onUpdate: function (e) {
                  return !!e;
                },
              },
            },
            i.default,
          ),
          c.default.component("zeroLoading", function () {
            n.e("components/zero-loading/zero-loading")
              .then(
                function () {
                  return resolve(n("f7e3"));
                }.bind(null, n),
              )
              .catch(n.oe);
          }),
          (c.default.prototype.imgsrc = function (e) {
            return i.default.state.dictVal
              ? e.includes(i.default.state.dictVal.uploadURL)
                ? e
                : (e.includes("/static") && (e = e.slice(8, e.length)),
                  i.default.state.dictVal.uploadURL + e)
              : e;
          }),
          (c.default.prototype.imgsrc1 = function (e) {
            return e;
          }),
          Object.keys(d).forEach(function (e) {
            c.default.filter(e, d[e]);
          });
        var O = new p.LOAD();
        O.beforeEach(function (e) {
          O[e.type](e);
        });
        var h = new p.HREF();
        (c.default.prototype.href = h.href.bind(h)),
          h.beforeEach(function (e) {
            !e.url ||
            -1 !==
              [
                "/pages/login/login",
                "/pages/home/home",
                "/pages/course/course",
                "/pages/member/member",
                "/pages/report/report",
                "/pages/shop/shop",
              ].indexOf(e.url.split("?")[0]) ||
            i.default.state.token
              ? e.auto ||
                (e.fail ||
                  (e.fail = function (e) {
                    console.log(e), t.switchTab({ url: "/" });
                  }),
                "navigateBack" === e.route &&
                  1 === getCurrentPages().length &&
                  t.switchTab({ url: "/" }),
                h[e.route || "navigateTo"](e))
              : i.default.dispatch("reLogin");
          }),
          c.default.use(b.default),
          c.default.mixin(l.default),
          o(
            new c.default(
              (function (e) {
                for (var t = 1; t < arguments.length; t++) {
                  var n = null != arguments[t] ? arguments[t] : {};
                  t % 2
                    ? g(Object(n), !0).forEach(function (t) {
                        (0, a.default)(e, t, n[t]);
                      })
                    : Object.getOwnPropertyDescriptors
                      ? Object.defineProperties(
                          e,
                          Object.getOwnPropertyDescriptors(n),
                        )
                      : g(Object(n)).forEach(function (t) {
                          Object.defineProperty(
                            e,
                            t,
                            Object.getOwnPropertyDescriptor(n, t),
                          );
                        });
                }
                return e;
              })({ store: i.default }, f.default),
            ),
          ).$mount();
      }).call(this, n("3223").default, n("df3c").default, n("df3c").createApp);
    },
    cc8d: function (e, t, n) {
      "use strict";
      n.r(t);
      var o = n("582c"),
        r = n.n(o);
      for (var u in o)
        ["default"].indexOf(u) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return o[e];
            });
          })(u);
      t.default = r.a;
    },
    faf9: function (e, t, n) {
      "use strict";
      var o = n("77c7");
      n.n(o).a;
    },
    fb83: function (e, t, n) {},
  },
  [["cb60", "common/runtime", "common/vendor"]],
]);
