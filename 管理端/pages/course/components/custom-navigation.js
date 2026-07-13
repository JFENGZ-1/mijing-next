(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/course/components/custom-navigation"],
  {
    "0b68": function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("d840"),
        a = e.n(o);
      for (var c in o)
        ["default"].indexOf(c) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(c);
      n.default = a.a;
    },
    6567: function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return o;
      }),
        e.d(n, "c", function () {
          return a;
        }),
        e.d(n, "a", function () {});
      var o = function () {
          this.$createElement;
          this._self._c;
        },
        a = [];
    },
    ae3f: function (t, n, e) {},
    ba6c: function (t, n, e) {
      "use strict";
      e.r(n);
      var o = e("6567"),
        a = e("0b68");
      for (var c in a)
        ["default"].indexOf(c) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return a[t];
            });
          })(c);
      e("ec04");
      var u = e("828b"),
        s = Object(u.a)(
          a.default,
          o.b,
          o.c,
          !1,
          null,
          "4adc7070",
          null,
          !1,
          o.a,
          void 0,
        );
      n.default = s.exports;
    },
    d840: function (t, n, e) {
      "use strict";
      (function (t) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var e = {
          name: "index",
          props: {
            text: { type: String, default: "" },
            background: { type: String, default: "#FBD128" },
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
      }).call(this, e("df3c").default);
    },
    ec04: function (t, n, e) {
      "use strict";
      var o = e("ae3f");
      e.n(o).a;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pages/course/components/custom-navigation-create-component",
    {
      "pages/course/components/custom-navigation-create-component": function (
        t,
        n,
        e,
      ) {
        e("df3c").createComponent(e("ba6c"));
      },
    },
    [["pages/course/components/custom-navigation-create-component"]],
  ]);
