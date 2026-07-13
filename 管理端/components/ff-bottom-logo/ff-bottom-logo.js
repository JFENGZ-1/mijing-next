(global.webpackJsonp = global.webpackJsonp || []).push([
  ["components/ff-bottom-logo/ff-bottom-logo"],
  {
    "19f3": function (t, o, n) {
      "use strict";
      var e = n("7716");
      n.n(e).a;
    },
    3111: function (t, o, n) {
      "use strict";
      n.r(o);
      var e = n("dbe2"),
        c = n("73cc");
      for (var f in c)
        ["default"].indexOf(f) < 0 &&
          (function (t) {
            n.d(o, t, function () {
              return c[t];
            });
          })(f);
      n("19f3");
      var a = n("828b"),
        i = Object(a.a)(
          c.default,
          e.b,
          e.c,
          !1,
          null,
          "9ae2f62c",
          null,
          !1,
          e.a,
          void 0,
        );
      o.default = i.exports;
    },
    6388: function (t, o, n) {
      "use strict";
      Object.defineProperty(o, "__esModule", { value: !0 }),
        (o.default = void 0);
      var e = {
        props: {
          dataType: {
            type: String,
            default: function () {
              return "1";
            },
          },
        },
      };
      o.default = e;
    },
    "73cc": function (t, o, n) {
      "use strict";
      n.r(o);
      var e = n("6388"),
        c = n.n(e);
      for (var f in e)
        ["default"].indexOf(f) < 0 &&
          (function (t) {
            n.d(o, t, function () {
              return e[t];
            });
          })(f);
      o.default = c.a;
    },
    7716: function (t, o, n) {},
    dbe2: function (t, o, n) {
      "use strict";
      n.d(o, "b", function () {
        return e;
      }),
        n.d(o, "c", function () {
          return c;
        }),
        n.d(o, "a", function () {});
      var e = function () {
          this.$createElement;
          var t =
              (this._self._c,
              1 == this.dataType
                ? this.imgsrc("/static/imgs/bottom_logo_1.png")
                : null),
            o =
              0 == this.dataType
                ? this.imgsrc("/static/imgs/bottom_logo-deep.png")
                : null;
          this.$mp.data = Object.assign({}, { $root: { m0: t, m1: o } });
        },
        c = [];
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "components/ff-bottom-logo/ff-bottom-logo-create-component",
    {
      "components/ff-bottom-logo/ff-bottom-logo-create-component": function (
        t,
        o,
        n,
      ) {
        n("df3c").createComponent(n("3111"));
      },
    },
    [["components/ff-bottom-logo/ff-bottom-logo-create-component"]],
  ]);
