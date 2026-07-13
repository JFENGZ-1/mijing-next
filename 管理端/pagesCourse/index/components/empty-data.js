(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesCourse/index/components/empty-data"],
  {
    "0e4f": function (t, n, e) {
      "use strict";
      e.r(n);
      var a = e("faba"),
        o = e.n(a);
      for (var c in a)
        ["default"].indexOf(c) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return a[t];
            });
          })(c);
      n.default = o.a;
    },
    4046: function (t, n, e) {
      "use strict";
      e.r(n);
      var a = e("6f9c"),
        o = e("0e4f");
      for (var c in o)
        ["default"].indexOf(c) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(c);
      e("887c");
      var u = e("828b"),
        r = Object(u.a)(
          o.default,
          a.b,
          a.c,
          !1,
          null,
          "0a13186e",
          null,
          !1,
          a.a,
          void 0,
        );
      n.default = r.exports;
    },
    "6f9c": function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return a;
      }),
        e.d(n, "c", function () {
          return o;
        }),
        e.d(n, "a", function () {});
      var a = function () {
          this.$createElement;
          var t = (this._self._c, this.imgsrc("/static/imgs/nodata.png"));
          this.$mp.data = Object.assign({}, { $root: { m0: t } });
        },
        o = [];
    },
    "7d2d": function (t, n, e) {},
    "887c": function (t, n, e) {
      "use strict";
      var a = e("7d2d");
      e.n(a).a;
    },
    faba: function (t, n, e) {
      "use strict";
      Object.defineProperty(n, "__esModule", { value: !0 }),
        (n.default = void 0),
        (n.default = {
          name: "nodata",
          props: { text: "" },
          data: function () {
            return {};
          },
        });
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pagesCourse/index/components/empty-data-create-component",
    {
      "pagesCourse/index/components/empty-data-create-component": function (
        t,
        n,
        e,
      ) {
        e("df3c").createComponent(e("4046"));
      },
    },
    [["pagesCourse/index/components/empty-data-create-component"]],
  ]);
