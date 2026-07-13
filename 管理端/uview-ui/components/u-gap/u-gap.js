(global.webpackJsonp = global.webpackJsonp || []).push([
  ["uview-ui/components/u-gap/u-gap"],
  {
    "2fb0": function (t, n, e) {
      "use strict";
      e.r(n);
      var a = e("3ff3"),
        o = e("f3d0");
      for (var u in o)
        ["default"].indexOf(u) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(u);
      e("9cce");
      var r = e("828b"),
        i = Object(r.a)(
          o.default,
          a.b,
          a.c,
          !1,
          null,
          "100f4740",
          null,
          !1,
          a.a,
          void 0,
        );
      n.default = i.exports;
    },
    "3ff3": function (t, n, e) {
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
          var t = (this._self._c, this.__get_style([this.gapStyle]));
          this.$mp.data = Object.assign({}, { $root: { s0: t } });
        },
        o = [];
    },
    "9cce": function (t, n, e) {
      "use strict";
      var a = e("dcfa");
      e.n(a).a;
    },
    a45a: function (t, n, e) {
      "use strict";
      Object.defineProperty(n, "__esModule", { value: !0 }),
        (n.default = void 0);
      var a = {
        name: "u-gap",
        props: {
          bgColor: { type: String, default: "transparent " },
          height: { type: [String, Number], default: 30 },
          marginTop: { type: [String, Number], default: 0 },
          marginBottom: { type: [String, Number], default: 0 },
        },
        computed: {
          gapStyle: function () {
            return {
              backgroundColor: this.bgColor,
              height: this.height + "rpx",
              marginTop: this.marginTop + "rpx",
              marginBottom: this.marginBottom + "rpx",
            };
          },
        },
      };
      n.default = a;
    },
    dcfa: function (t, n, e) {},
    f3d0: function (t, n, e) {
      "use strict";
      e.r(n);
      var a = e("a45a"),
        o = e.n(a);
      for (var u in a)
        ["default"].indexOf(u) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return a[t];
            });
          })(u);
      n.default = o.a;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "uview-ui/components/u-gap/u-gap-create-component",
    {
      "uview-ui/components/u-gap/u-gap-create-component": function (t, n, e) {
        e("df3c").createComponent(e("2fb0"));
      },
    },
    [["uview-ui/components/u-gap/u-gap-create-component"]],
  ]);
