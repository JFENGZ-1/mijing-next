(global.webpackJsonp = global.webpackJsonp || []).push([
  ["uview-ui/components/u-loadmore/u-loadmore"],
  {
    "15ff": function (t, e, o) {
      "use strict";
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.default = void 0);
      var n = {
        name: "u-loadmore",
        props: {
          bgColor: { type: String, default: "transparent" },
          icon: { type: Boolean, default: !0 },
          fontSize: { type: String, default: "28" },
          color: { type: String, default: "#606266" },
          status: { type: String, default: "loadmore" },
          iconType: { type: String, default: "circle" },
          loadText: {
            type: Object,
            default: function () {
              return {
                loadmore: "加载更多",
                loading: "正在加载...",
                nomore: "没有更多了",
              };
            },
          },
          isDot: { type: Boolean, default: !1 },
          iconColor: { type: String, default: "#b7b7b7" },
          marginTop: { type: [String, Number], default: 0 },
          marginBottom: { type: [String, Number], default: 0 },
          height: { type: [String, Number], default: "auto" },
        },
        data: function () {
          return { dotText: "●" };
        },
        computed: {
          loadTextStyle: function () {
            return {
              color: this.color,
              fontSize: this.fontSize + "rpx",
              position: "relative",
              zIndex: 1,
              backgroundColor: this.bgColor,
            };
          },
          cricleStyle: function () {
            return {
              borderColor: "#e5e5e5 #e5e5e5 #e5e5e5 ".concat(this.circleColor),
            };
          },
          flowerStyle: function () {
            return {};
          },
          showText: function () {
            return "loadmore" == this.status
              ? this.loadText.loadmore
              : "loading" == this.status
                ? this.loadText.loading
                : "nomore" == this.status && this.isDot
                  ? this.dotText
                  : this.loadText.nomore;
          },
        },
        methods: {
          loadMore: function () {
            "loadmore" == this.status && this.$emit("loadmore");
          },
        },
      };
      e.default = n;
    },
    4517: function (t, e, o) {
      "use strict";
      o.r(e);
      var n = o("6cfb"),
        i = o("fdc2");
      for (var u in i)
        ["default"].indexOf(u) < 0 &&
          (function (t) {
            o.d(e, t, function () {
              return i[t];
            });
          })(u);
      o("7216");
      var r = o("828b"),
        a = Object(r.a)(
          i.default,
          n.b,
          n.c,
          !1,
          null,
          "f8168008",
          null,
          !1,
          n.a,
          void 0,
        );
      e.default = a.exports;
    },
    "6cfb": function (t, e, o) {
      "use strict";
      o.d(e, "b", function () {
        return i;
      }),
        o.d(e, "c", function () {
          return u;
        }),
        o.d(e, "a", function () {
          return n;
        });
      var n = {
          uLine: function () {
            return o
              .e("uview-ui/components/u-line/u-line")
              .then(o.bind(null, "fac3"));
          },
          uLoading: function () {
            return o
              .e("uview-ui/components/u-loading/u-loading")
              .then(o.bind(null, "ebb2"));
          },
        },
        i = function () {
          this.$createElement;
          var t = (this._self._c, this.$u.addUnit(this.height)),
            e = this.__get_style([this.loadTextStyle]);
          this.$mp.data = Object.assign({}, { $root: { g0: t, s0: e } });
        },
        u = [];
    },
    7216: function (t, e, o) {
      "use strict";
      var n = o("e627");
      o.n(n).a;
    },
    e627: function (t, e, o) {},
    fdc2: function (t, e, o) {
      "use strict";
      o.r(e);
      var n = o("15ff"),
        i = o.n(n);
      for (var u in n)
        ["default"].indexOf(u) < 0 &&
          (function (t) {
            o.d(e, t, function () {
              return n[t];
            });
          })(u);
      e.default = i.a;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "uview-ui/components/u-loadmore/u-loadmore-create-component",
    {
      "uview-ui/components/u-loadmore/u-loadmore-create-component": function (
        t,
        e,
        o,
      ) {
        o("df3c").createComponent(o("4517"));
      },
    },
    [["uview-ui/components/u-loadmore/u-loadmore-create-component"]],
  ]);
