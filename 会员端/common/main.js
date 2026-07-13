require("../@babel/runtime/helpers/Arrayincludes"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["common/main"],
    {
      "0a11": function (e, t, n) {
        n.r(t);
        var o = n("7127");
        for (var r in o)
          ["default"].indexOf(r) < 0 &&
            (function (e) {
              n.d(t, e, function () {
                return o[e];
              });
            })(r);
        n("6b05"), n("2087");
        var a = n("828b"),
          u = Object(a.a)(
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
        t.default = u.exports;
      },
      "117e": function (e, t, n) {},
      2087: function (e, t, n) {
        var o = n("117e");
        n.n(o).a;
      },
      "5b51": function (e, t, n) {},
      "623c": function (e, t, n) {
        (function (e, t) {
          var o = n("47a9"),
            r = o(n("7ca3"));
          n("9785");
          var a = o(n("0a11")),
            u = (o(n("bd1e")), o(n("d91c"))),
            c = o(n("3240")),
            f = o(n("4158")),
            i = o(n("7dc1")),
            l = o(n("7fba"));
          function d(e, t) {
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
          function s(e) {
            for (var t = 1; t < arguments.length; t++) {
              var n = null != arguments[t] ? arguments[t] : {};
              t % 2
                ? d(Object(n), !0).forEach(function (t) {
                    (0, r.default)(e, t, n[t]);
                  })
                : Object.getOwnPropertyDescriptors
                  ? Object.defineProperties(
                      e,
                      Object.getOwnPropertyDescriptors(n),
                    )
                  : d(Object(n)).forEach(function (t) {
                      Object.defineProperty(
                        e,
                        t,
                        Object.getOwnPropertyDescriptor(n, t),
                      );
                    });
            }
            return e;
          }
          (e.__webpack_require_UNI_MP_PLUGIN__ = n),
            c.default.mixin(l.default),
            c.default.use(
              u.default,
              {
                UserInfo: {
                  name: "UserInfo",
                  watchKey: "userInfo",
                  deep: !0,
                  onUpdate: function (e) {
                    return !!e;
                  },
                },
              },
              i.default,
            ),
            c.default.use(f.default),
            c.default.component("bottom-logo", function () {
              n.e("components/ff-bottom-logo/index")
                .then(
                  function () {
                    return resolve(n("0f25"));
                  }.bind(null, n),
                )
                .catch(n.oe);
            }),
            (c.default.config.productionTip = !1),
            (a.default.mpType = "app"),
            (c.default.prototype.imgsrc = function (e) {
              return i.default.state.commonData
                ? e.includes(i.default.state.commonData.uploadURL)
                  ? e
                  : (e.includes("/static") && (e = e.slice(8, e.length)),
                    i.default.state.commonData.uploadURL + e)
                : e;
            }),
            (c.default.prototype.imgsrc1 = function (e) {
              return e;
            }),
            t(
              new c.default(s(s({}, a.default), {}, { store: i.default })),
            ).$mount();
        }).call(this, n("3223").default, n("df3c").createApp);
      },
      "6b05": function (e, t, n) {
        var o = n("5b51");
        n.n(o).a;
      },
      7127: function (e, t, n) {
        n.r(t);
        var o = n("e155"),
          r = n.n(o);
        for (var a in o)
          ["default"].indexOf(a) < 0 &&
            (function (e) {
              n.d(t, e, function () {
                return o[e];
              });
            })(a);
        t.default = r.a;
      },
      e155: function (e, t, n) {
        (function (e) {
          Object.defineProperty(t, "__esModule", { value: !0 }),
            (t.default = void 0);
          var n = {
            onLaunch: function () {
              this.$store.dispatch("getSystemInfo"),
                this.$store.dispatch("getCommon"),
                e.removeStorageSync("skipDate"),
                e.removeStorageSync("siteId");
            },
            onShow: function () {},
            onHide: function () {},
          };
          t.default = n;
        }).call(this, n("df3c").default);
      },
    },
    [["623c", "common/runtime", "common/vendor"]],
  ]);
