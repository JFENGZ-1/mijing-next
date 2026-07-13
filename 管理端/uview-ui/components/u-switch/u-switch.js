(global.webpackJsonp = global.webpackJsonp || []).push([
  ["uview-ui/components/u-switch/u-switch"],
  {
    "08f5": function (t, e, i) {
      "use strict";
      var n = i("dab3");
      i.n(n).a;
    },
    "576c": function (t, e, i) {
      "use strict";
      (function (t) {
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var i = {
          name: "u-switch",
          props: {
            loading: { type: Boolean, default: !1 },
            disabled: { type: Boolean, default: !1 },
            size: { type: [Number, String], default: 50 },
            activeColor: { type: String, default: "#22C788" },
            inactiveColor: { type: String, default: "#F5F5F5" },
            value: { type: Boolean, default: !1 },
            vibrateShort: { type: Boolean, default: !1 },
            activeValue: { type: [Number, String, Boolean], default: !0 },
            inactiveValue: { type: [Number, String, Boolean], default: !1 },
          },
          data: function () {
            return {};
          },
          computed: {
            switchStyle: function () {
              var t = {};
              return (
                (t.fontSize = this.size + "rpx"),
                (t.backgroundColor = this.value
                  ? this.activeColor
                  : this.inactiveColor),
                t
              );
            },
            loadingColor: function () {
              return this.value ? this.activeColor : null;
            },
          },
          methods: {
            onClick: function () {
              var e = this;
              this.disabled ||
                this.loading ||
                (this.vibrateShort && t.vibrateShort(),
                this.$emit("input", !this.value),
                this.$nextTick(function () {
                  e.$emit("change", e.value ? e.activeValue : e.inactiveValue);
                }));
            },
          },
        };
        e.default = i;
      }).call(this, i("df3c").default);
    },
    "862c2": function (t, e, i) {
      "use strict";
      i.r(e);
      var n = i("576c"),
        u = i.n(n);
      for (var a in n)
        ["default"].indexOf(a) < 0 &&
          (function (t) {
            i.d(e, t, function () {
              return n[t];
            });
          })(a);
      e.default = u.a;
    },
    "96c3": function (t, e, i) {
      "use strict";
      i.d(e, "b", function () {
        return u;
      }),
        i.d(e, "c", function () {
          return a;
        }),
        i.d(e, "a", function () {
          return n;
        });
      var n = {
          uLoading: function () {
            return i
              .e("uview-ui/components/u-loading/u-loading")
              .then(i.bind(null, "ebb2"));
          },
        },
        u = function () {
          this.$createElement;
          var t = (this._self._c, this.__get_style([this.switchStyle])),
            e = this.$u.addUnit(this.size),
            i = this.$u.addUnit(this.size);
          this.$mp.data = Object.assign({}, { $root: { s0: t, g0: e, g1: i } });
        },
        a = [];
    },
    a048: function (t, e, i) {
      "use strict";
      i.r(e);
      var n = i("96c3"),
        u = i("862c2");
      for (var a in u)
        ["default"].indexOf(a) < 0 &&
          (function (t) {
            i.d(e, t, function () {
              return u[t];
            });
          })(a);
      i("08f5");
      var o = i("828b"),
        c = Object(o.a)(
          u.default,
          n.b,
          n.c,
          !1,
          null,
          "276d8a8c",
          null,
          !1,
          n.a,
          void 0,
        );
      e.default = c.exports;
    },
    dab3: function (t, e, i) {},
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "uview-ui/components/u-switch/u-switch-create-component",
    {
      "uview-ui/components/u-switch/u-switch-create-component": function (
        t,
        e,
        i,
      ) {
        i("df3c").createComponent(i("a048"));
      },
    },
    [["uview-ui/components/u-switch/u-switch-create-component"]],
  ]);
