(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageServer/components/service-bottom-logo"],
  {
    "04b4": function (e, t, o) {},
    "459b": function (e, t, o) {
      "use strict";
      var n = o("04b4");
      o.n(n).a;
    },
    "86f2": function (e, t, o) {
      "use strict";
      o.d(t, "b", function () {
        return n;
      }),
        o.d(t, "c", function () {
          return a;
        }),
        o.d(t, "a", function () {});
      var n = function () {
          this.$createElement;
          var e =
              (this._self._c,
              1 == this.dataType
                ? this.imgsrc("/static/imgs/bottom_logo_1.png")
                : null),
            t =
              0 == this.dataType
                ? this.imgsrc("/static/imgs/bottom_logo-deep.png")
                : null;
          this.$mp.data = Object.assign({}, { $root: { m0: e, m1: t } });
        },
        a = [];
    },
    "9bf1": function (e, t, o) {
      "use strict";
      o.r(t);
      var n = o("cefea"),
        a = o.n(n);
      for (var c in n)
        ["default"].indexOf(c) < 0 &&
          (function (e) {
            o.d(t, e, function () {
              return n[e];
            });
          })(c);
      t.default = a.a;
    },
    a3ef: function (e, t, o) {
      "use strict";
      o.r(t);
      var n = o("86f2"),
        a = o("9bf1");
      for (var c in a)
        ["default"].indexOf(c) < 0 &&
          (function (e) {
            o.d(t, e, function () {
              return a[e];
            });
          })(c);
      o("459b");
      var r = o("828b"),
        i = Object(r.a)(
          a.default,
          n.b,
          n.c,
          !1,
          null,
          "0e79ad11",
          null,
          !1,
          n.a,
          void 0,
        );
      t.default = i.exports;
    },
    cefea: function (e, t, o) {
      "use strict";
      Object.defineProperty(t, "__esModule", { value: !0 }),
        (t.default = void 0);
      var n = {
        props: {
          dataType: {
            type: String,
            default: function () {
              return "1";
            },
          },
        },
      };
      t.default = n;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pageServer/components/service-bottom-logo-create-component",
    {
      "pageServer/components/service-bottom-logo-create-component": function (
        e,
        t,
        o,
      ) {
        o("df3c").createComponent(o("a3ef"));
      },
    },
    [["pageServer/components/service-bottom-logo-create-component"]],
  ]);
