require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageMember/components/editPoint"],
    {
      "048e": function (n, t, e) {
        "use strict";
        e.r(t);
        var o = e("7d06"),
          i = e.n(o);
        for (var r in o)
          ["default"].indexOf(r) < 0 &&
            (function (n) {
              e.d(t, n, function () {
                return o[n];
              });
            })(r);
        t.default = i.a;
      },
      "450f": function (n, t, e) {
        "use strict";
        e.r(t);
        var o = e("b56f"),
          i = e("048e");
        for (var r in i)
          ["default"].indexOf(r) < 0 &&
            (function (n) {
              e.d(t, n, function () {
                return i[n];
              });
            })(r);
        e("948b");
        var s = e("828b"),
          a = Object(s.a)(
            i.default,
            o.b,
            o.c,
            !1,
            null,
            "ac6c96a0",
            null,
            !1,
            o.a,
            void 0,
          );
        t.default = a.exports;
      },
      7539: function (n, t, e) {},
      "7d06": function (n, t, e) {
        "use strict";
        (function (n) {
          Object.defineProperty(t, "__esModule", { value: !0 }),
            (t.default = void 0);
          var o = e("4689"),
            i = {
              data: function () {
                return {
                  show: !1,
                  nnid: 0,
                  pointVal: "",
                  reasonText: "",
                  title: "积分设置",
                  titlePre: "",
                  placeholder: "填积分",
                  placeholder1: "填写原由/用途",
                  inputStyle1: {
                    fontSize: "35rpx",
                    paddingLeft: "28rpx",
                    background: "#f5f5f5",
                    margin: "10rpx 22rpx",
                    borderRadius: "20px",
                    color: "#6b6b6c",
                    width: "500rpx",
                  },
                  inputStyle: {
                    fontSize: "35rpx",
                    paddingLeft: "28rpx",
                    background: "#f5f5f5",
                    margin: "10rpx 22rpx",
                    borderRadius: "20px",
                    color: "#6b6b6c",
                    width: "187rpx",
                  },
                };
              },
              methods: {
                submit: function () {
                  if (this.pointVal)
                    if (/^[1-9]\d*$/.test(this.pointVal))
                      if (this.reasonText) {
                        var t = {};
                        5 == this.reasonId
                          ? (t.pointVal = -this.pointVal)
                          : (t.pointVal = this.pointVal),
                          (t.reasonText = this.reasonText),
                          (t.nnid = this.nnid);
                        var e = this;
                        (0, o.editPointLog)(t).then(function (t) {
                          200 == t.code
                            ? n.showToast({
                                title: "修改积分成功",
                                icon: "none",
                                mask: !0,
                                success: function () {
                                  e.$emit("submit"), (e.show = !1);
                                },
                              })
                            : n.showToast({
                                title: t.msg,
                                icon: "none",
                                mask: !0,
                              });
                        });
                      } else
                        n.showToast({
                          title: "请填写原由/用途",
                          duration: 2e3,
                          icon: "none",
                        });
                    else
                      n.showToast({
                        title: "积分必须是正整数",
                        duration: 2e3,
                        icon: "none",
                      });
                  else
                    n.showToast({
                      title: "请输入积分",
                      duration: 2e3,
                      icon: "none",
                    });
                },
                confirm: function () {},
                open: function (n) {
                  console.log(n),
                    (this.pointVal = n.pointVal),
                    (this.reasonId = n.reasonId),
                    console.log(this.reasonId),
                    4 == this.reasonId
                      ? (this.titlePre = "加")
                      : ((this.titlePre = "减"),
                        (this.pointVal = Math.abs(this.pointVal))),
                    (this.reasonText = n.pointTitle),
                    (this.nnid = n.nnid),
                    (this.show = !0);
                },
              },
              computed: {},
            };
          t.default = i;
        }).call(this, e("df3c").default);
      },
      "948b": function (n, t, e) {
        "use strict";
        var o = e("7539");
        e.n(o).a;
      },
      b56f: function (n, t, e) {
        "use strict";
        e.d(t, "b", function () {
          return i;
        }),
          e.d(t, "c", function () {
            return r;
          }),
          e.d(t, "a", function () {
            return o;
          });
        var o = {
            ffPopup: function () {
              return e
                .e("components/ff-popup/ff-popup")
                .then(e.bind(null, "c29b"));
            },
            uInput: function () {
              return Promise.all([
                e.e("common/vendor"),
                e.e("uview-ui/components/u-input/u-input"),
              ]).then(e.bind(null, "b5ea"));
            },
            uButton: function () {
              return e
                .e("uview-ui/components/u-button/u-button")
                .then(e.bind(null, "d5d3"));
            },
          },
          i = function () {
            this.$createElement;
            this._self._c;
          },
          r = [];
      },
    },
  ]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pageMember/components/editPoint-create-component",
    {
      "pageMember/components/editPoint-create-component": function (n, t, e) {
        e("df3c").createComponent(e("450f"));
      },
    },
    [["pageMember/components/editPoint-create-component"]],
  ]);
