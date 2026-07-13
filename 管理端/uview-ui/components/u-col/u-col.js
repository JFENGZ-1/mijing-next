(global.webpackJsonp = global.webpackJsonp || []).push([
  ["uview-ui/components/u-col/u-col"],
  {
    "026a": function (t, e, n) {},
    "0663": function (t, e, n) {
      "use strict";
      n.r(e);
      var u = n("08b4"),
        i = n("57d4");
      for (var o in i)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return i[t];
            });
          })(o);
      n("3d07");
      var a = n("828b"),
        c = Object(a.a)(
          i.default,
          u.b,
          u.c,
          !1,
          null,
          null,
          null,
          !1,
          u.a,
          void 0,
        );
      e.default = c.exports;
    },
    "08b4": function (t, e, n) {
      "use strict";
      n.d(e, "b", function () {
        return u;
      }),
        n.d(e, "c", function () {
          return i;
        }),
        n.d(e, "a", function () {});
      var u = function () {
          this.$createElement;
          var t = (this._self._c, Number(this.gutter));
          this.$mp.data = Object.assign({}, { $root: { m0: t } });
        },
        i = [];
    },
    1283: function (t, e, n) {
      "use strict";
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.default = void 0);
      var u = {
        name: "u-col",
        props: {
          span: { type: [Number, String], default: 12 },
          offset: { type: [Number, String], default: 0 },
          justify: { type: String, default: "start" },
          align: { type: String, default: "center" },
          textAlign: { type: String, default: "left" },
          stop: { type: Boolean, default: !0 },
        },
        data: function () {
          return { gutter: 20 };
        },
        created: function () {
          this.parent = !1;
        },
        mounted: function () {
          (this.parent = this.$u.$parent.call(this, "u-row")),
            this.parent && (this.gutter = this.parent.gutter);
        },
        computed: {
          uJustify: function () {
            return "end" == this.justify || "start" == this.justify
              ? "flex-" + this.justify
              : "around" == this.justify || "between" == this.justify
                ? "space-" + this.justify
                : this.justify;
          },
          uAlignItem: function () {
            return "top" == this.align
              ? "flex-start"
              : "bottom" == this.align
                ? "flex-end"
                : this.align;
          },
        },
        methods: {
          click: function (t) {
            this.$emit("click");
          },
        },
      };
      e.default = u;
    },
    "3d07": function (t, e, n) {
      "use strict";
      var u = n("026a");
      n.n(u).a;
    },
    "57d4": function (t, e, n) {
      "use strict";
      n.r(e);
      var u = n("1283"),
        i = n.n(u);
      for (var o in u)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            n.d(e, t, function () {
              return u[t];
            });
          })(o);
      e.default = i.a;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "uview-ui/components/u-col/u-col-create-component",
    {
      "uview-ui/components/u-col/u-col-create-component": function (t, e, n) {
        n("df3c").createComponent(n("0663"));
      },
    },
    [["uview-ui/components/u-col/u-col-create-component"]],
  ]);
