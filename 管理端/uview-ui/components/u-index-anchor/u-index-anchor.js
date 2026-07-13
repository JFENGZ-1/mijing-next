(global.webpackJsonp = global.webpackJsonp || []).push([
  ["uview-ui/components/u-index-anchor/u-index-anchor"],
  {
    "18d4": function (t, n, e) {
      "use strict";
      e.r(n);
      var a = e("f4a4"),
        u = e("78e1");
      for (var i in u)
        ["default"].indexOf(i) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return u[t];
            });
          })(i);
      e("83a9");
      var o = e("828b"),
        c = Object(o.a)(
          u.default,
          a.b,
          a.c,
          !1,
          null,
          "2b03103d",
          null,
          !1,
          a.a,
          void 0,
        );
      n.default = c.exports;
    },
    "78e1": function (t, n, e) {
      "use strict";
      e.r(n);
      var a = e("eadc"),
        u = e.n(a);
      for (var i in a)
        ["default"].indexOf(i) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return a[t];
            });
          })(i);
      n.default = u.a;
    },
    "83a9": function (t, n, e) {
      "use strict";
      var a = e("a359");
      e.n(a).a;
    },
    a359: function (t, n, e) {},
    eadc: function (t, n, e) {
      "use strict";
      Object.defineProperty(n, "__esModule", { value: !0 }),
        (n.default = void 0);
      var a = {
        name: "u-index-anchor",
        props: {
          useSlot: { type: Boolean, default: !1 },
          index: { type: String, default: "" },
          customStyle: {
            type: Object,
            default: function () {
              return {};
            },
          },
        },
        data: function () {
          return { active: !1, wrapperStyle: {}, anchorStyle: {} };
        },
        created: function () {
          this.parent = !1;
        },
        mounted: function () {
          (this.parent = this.$u.$parent.call(this, "u-index-list")),
            this.parent &&
              (this.parent.children.push(this), this.parent.updateData());
        },
        computed: {
          customAnchorStyle: function () {
            return Object.assign(this.anchorStyle, this.customStyle);
          },
        },
      };
      n.default = a;
    },
    f4a4: function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return a;
      }),
        e.d(n, "c", function () {
          return u;
        }),
        e.d(n, "a", function () {});
      var a = function () {
          this.$createElement;
          var t = (this._self._c, this.__get_style([this.wrapperStyle])),
            n = this.$u.guid(),
            e = this.__get_style([this.customAnchorStyle]);
          this.$mp.data = Object.assign({}, { $root: { s0: t, g0: n, s1: e } });
        },
        u = [];
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "uview-ui/components/u-index-anchor/u-index-anchor-create-component",
    {
      "uview-ui/components/u-index-anchor/u-index-anchor-create-component":
        function (t, n, e) {
          e("df3c").createComponent(e("18d4"));
        },
    },
    [["uview-ui/components/u-index-anchor/u-index-anchor-create-component"]],
  ]);
