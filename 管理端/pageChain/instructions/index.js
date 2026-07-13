(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageChain/instructions/index"],
  {
    "0799": function (t, n, i) {
      "use strict";
      i.r(n);
      var e = i("7306"),
        o = i.n(e);
      for (var c in e)
        ["default"].indexOf(c) < 0 &&
          (function (t) {
            i.d(n, t, function () {
              return e[t];
            });
          })(c);
      n.default = o.a;
    },
    "164f": function (t, n, i) {
      "use strict";
      i.r(n);
      var e = i("5cba"),
        o = i("0799");
      for (var c in o)
        ["default"].indexOf(c) < 0 &&
          (function (t) {
            i.d(n, t, function () {
              return o[t];
            });
          })(c);
      i("9a24");
      var u = i("828b"),
        a = Object(u.a)(
          o.default,
          e.b,
          e.c,
          !1,
          null,
          "ce8c5d7c",
          null,
          !1,
          e.a,
          void 0,
        );
      n.default = a.exports;
    },
    "354e": function (t, n, i) {},
    "5cba": function (t, n, i) {
      "use strict";
      i.d(n, "b", function () {
        return o;
      }),
        i.d(n, "c", function () {
          return c;
        }),
        i.d(n, "a", function () {
          return e;
        });
      var e = {
          ffBottomLogo: function () {
            return i
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(i.bind(null, "3111"));
          },
        },
        o = function () {
          this.$createElement;
          var t =
              (this._self._c,
              this.imgsrc("/unioncard/multiple-config-subbranch.png")),
            n = this.imgsrc("/unioncard/multiple-config-card.png"),
            i = this.imgsrc("/unioncard/multiple-config-shop-course.png"),
            e = this.imgsrc("/unioncard/multiple-config-staff.png"),
            o = this.imgsrc("/unioncard/multiple-config-card-statistics.png"),
            c = this.imgsrc("/unioncard/multiple-config-course-statistics.png");
          this.$mp.data = Object.assign(
            {},
            { $root: { m0: t, m1: n, m2: i, m3: e, m4: o, m5: c } },
          );
        },
        c = [];
    },
    6981: function (t, n, i) {
      "use strict";
      (function (t, n) {
        var e = i("47a9");
        i("86d2"), e(i("3240"));
        var o = e(i("164f"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = i), n(o.default);
      }).call(this, i("3223").default, i("df3c").createPage);
    },
    7306: function (t, n, i) {
      "use strict";
      (function (t) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0),
          i("1ba0");
        var e = {
          name: "index",
          components: {
            navigation: function () {
              i.e("components/navigation/index")
                .then(
                  function () {
                    return resolve(i("af9e"));
                  }.bind(null, i),
                )
                .catch(i.oe);
            },
          },
          data: function () {
            return {};
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
          methods: {},
        };
        n.default = e;
      }).call(this, i("df3c").default);
    },
    "9a24": function (t, n, i) {
      "use strict";
      var e = i("354e");
      i.n(e).a;
    },
  },
  [["6981", "common/runtime", "common/vendor"]],
]);
