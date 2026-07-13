(global.webpackJsonp = global.webpackJsonp || []).push([
  ["uview-ui/components/u-tag/u-tag"],
  {
    "0844": function (t, e, o) {
      "use strict";
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.default = void 0);
      var n = {
        name: "u-tag",
        props: {
          type: { type: String, default: "primary" },
          disabled: { type: [Boolean, String], default: !1 },
          size: { type: String, default: "default" },
          shape: { type: String, default: "square" },
          text: { type: [String, Number], default: "" },
          bgColor: { type: String, default: "" },
          color: { type: String, default: "" },
          borderColor: { type: String, default: "" },
          closeColor: { type: String, default: "" },
          index: { type: [Number, String], default: "" },
          mode: { type: String, default: "light" },
          closeable: { type: Boolean, default: !1 },
          show: { type: Boolean, default: !0 },
        },
        data: function () {
          return {};
        },
        computed: {
          customStyle: function () {
            var t = {};
            return (
              this.color && (t.color = this.color),
              this.bgColor && (t.backgroundColor = this.bgColor),
              "plain" == this.mode && this.color && !this.borderColor
                ? (t.borderColor = this.color)
                : (t.borderColor = this.borderColor),
              t
            );
          },
          iconStyle: function () {
            if (this.closeable) {
              var t = {};
              return (
                "mini" == this.size
                  ? (t.fontSize = "20rpx")
                  : (t.fontSize = "22rpx"),
                "plain" == this.mode || "light" == this.mode
                  ? (t.color = this.type)
                  : "dark" == this.mode && (t.color = "#ffffff"),
                this.closeColor && (t.color = this.closeColor),
                t
              );
            }
          },
          closeIconColor: function () {
            return this.closeColor
              ? this.closeColor
              : this.color
                ? this.color
                : "dark" == this.mode
                  ? "#ffffff"
                  : this.type;
          },
        },
        methods: {
          clickTag: function () {
            this.disabled || this.$emit("click", this.index);
          },
          close: function () {
            this.$emit("close", this.index);
          },
        },
      };
      e.default = n;
    },
    "109d": function (t, e, o) {},
    "1c75": function (t, e, o) {
      "use strict";
      o.d(e, "b", function () {
        return i;
      }),
        o.d(e, "c", function () {
          return r;
        }),
        o.d(e, "a", function () {
          return n;
        });
      var n = {
          uIcon: function () {
            return o
              .e("uview-ui/components/u-icon/u-icon")
              .then(o.bind(null, "81af"));
          },
        },
        i = function () {
          var t = this,
            e =
              (t.$createElement,
              t._self._c,
              t.show ? t.__get_style([t.customStyle]) : null),
            o = t.show && t.closeable ? t.__get_style([t.iconStyle]) : null;
          t.$mp.data = Object.assign({}, { $root: { s0: e, s1: o } });
        },
        r = [];
    },
    "88ae": function (t, e, o) {
      "use strict";
      o.r(e);
      var n = o("1c75"),
        i = o("edf1");
      for (var r in i)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            o.d(e, t, function () {
              return i[t];
            });
          })(r);
      o("c9f5");
      var l = o("828b"),
        u = Object(l.a)(
          i.default,
          n.b,
          n.c,
          !1,
          null,
          "48a4c226",
          null,
          !1,
          n.a,
          void 0,
        );
      e.default = u.exports;
    },
    c9f5: function (t, e, o) {
      "use strict";
      var n = o("109d");
      o.n(n).a;
    },
    edf1: function (t, e, o) {
      "use strict";
      o.r(e);
      var n = o("0844"),
        i = o.n(n);
      for (var r in n)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            o.d(e, t, function () {
              return n[t];
            });
          })(r);
      e.default = i.a;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "uview-ui/components/u-tag/u-tag-create-component",
    {
      "uview-ui/components/u-tag/u-tag-create-component": function (t, e, o) {
        o("df3c").createComponent(o("88ae"));
      },
    },
    [["uview-ui/components/u-tag/u-tag-create-component"]],
  ]);
