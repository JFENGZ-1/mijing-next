require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/embershipAgreement/index"],
    {
      "0b96": function (t, n, e) {
        "use strict";
        var o = e("5c01");
        e.n(o).a;
      },
      2748: function (t, n, e) {
        "use strict";
        (function (t) {
          Object.defineProperty(n, "__esModule", { value: !0 }),
            (n.default = void 0);
          var o = e("b680"),
            u = {
              data: function () {
                return { textdata: "" };
              },
              components: {
                navigation: function () {
                  e.e("components/navigation/index")
                    .then(
                      function () {
                        return resolve(e("af9e"));
                      }.bind(null, e),
                    )
                    .catch(e.oe);
                },
              },
              computed: {
                StatusBar: function () {
                  return this.$store.state.systemInfo.statusBarHeight;
                },
                CustomBar: function () {
                  var n = t.getMenuButtonBoundingClientRect();
                  return (
                    n.height +
                    2 * (n.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                    2
                  );
                },
              },
              methods: {
                getList: function () {
                  var n = this;
                  (0, o.getuserProtocolSetting)().then(function (e) {
                    200 == e.code
                      ? (n.textdata = e.data)
                      : t.showToast({ icon: "none", title: e.msg });
                  });
                },
              },
              onLoad: function () {},
              onShow: function () {
                this.getList();
              },
            };
          n.default = u;
        }).call(this, e("df3c").default);
      },
      "5c01": function (t, n, e) {},
      "8fb8b": function (t, n, e) {
        "use strict";
        e.r(n);
        var o = e("a325"),
          u = e("d272");
        for (var i in u)
          ["default"].indexOf(i) < 0 &&
            (function (t) {
              e.d(n, t, function () {
                return u[t];
              });
            })(i);
        e("0b96");
        var a = e("828b"),
          c = Object(a.a)(
            u.default,
            o.b,
            o.c,
            !1,
            null,
            "5b865632",
            null,
            !1,
            o.a,
            void 0,
          );
        n.default = c.exports;
      },
      "9cc5": function (t, n, e) {
        "use strict";
        (function (t, n) {
          var o = e("47a9");
          e("86d2"), o(e("3240"));
          var u = o(e("8fb8b"));
          (t.__webpack_require_UNI_MP_PLUGIN__ = e), n(u.default);
        }).call(this, e("3223").default, e("df3c").createPage);
      },
      a325: function (t, n, e) {
        "use strict";
        e.d(n, "b", function () {
          return u;
        }),
          e.d(n, "c", function () {
            return i;
          }),
          e.d(n, "a", function () {
            return o;
          });
        var o = {
            uIcon: function () {
              return e
                .e("uview-ui/components/u-icon/u-icon")
                .then(e.bind(null, "81af"));
            },
            nodata: function () {
              return e.e("components/nodata/nodata").then(e.bind(null, "4c3d"));
            },
            ffBottomLogo: function () {
              return e
                .e("components/ff-bottom-logo/ff-bottom-logo")
                .then(e.bind(null, "3111"));
            },
          },
          u = function () {
            this.$createElement;
            this._self._c;
          },
          i = [];
      },
      d272: function (t, n, e) {
        "use strict";
        e.r(n);
        var o = e("2748"),
          u = e.n(o);
        for (var i in o)
          ["default"].indexOf(i) < 0 &&
            (function (t) {
              e.d(n, t, function () {
                return o[t];
              });
            })(i);
        n.default = u.a;
      },
    },
    [["9cc5", "common/runtime", "common/vendor", "pageConfig/common/vendor"]],
  ]);
