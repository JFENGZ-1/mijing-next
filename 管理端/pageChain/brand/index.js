(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageChain/brand/index"],
  {
    "035e": function (n, t, o) {
      "use strict";
      o.r(t);
      var e = o("e2bd"),
        i = o.n(e);
      for (var u in e)
        ["default"].indexOf(u) < 0 &&
          (function (n) {
            o.d(t, n, function () {
              return e[n];
            });
          })(u);
      t.default = i.a;
    },
    2804: function (n, t, o) {
      "use strict";
      o.d(t, "b", function () {
        return i;
      }),
        o.d(t, "c", function () {
          return u;
        }),
        o.d(t, "a", function () {
          return e;
        });
      var e = {
          uInput: function () {
            return Promise.all([
              o.e("common/vendor"),
              o.e("uview-ui/components/u-input/u-input"),
            ]).then(o.bind(null, "b5ea"));
          },
          ffBottomLogo: function () {
            return o
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(o.bind(null, "3111"));
          },
        },
        i = function () {
          this.$createElement;
          var n = (this._self._c, this.imgsrc("/static/imgs/camera.png"));
          this.$mp.data = Object.assign({}, { $root: { m0: n } });
        },
        u = [];
    },
    c1ff: function (n, t, o) {
      "use strict";
      (function (n, t) {
        var e = o("47a9");
        o("86d2"), e(o("3240"));
        var i = e(o("f471"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = o), t(i.default);
      }).call(this, o("3223").default, o("df3c").createPage);
    },
    d54c: function (n, t, o) {
      "use strict";
      var e = o("ea6b");
      o.n(e).a;
    },
    e2bd: function (n, t, o) {
      "use strict";
      (function (n) {
        var e = o("47a9");
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var i = o("1ba0"),
          u = e(o("7502")),
          a = {
            data: function () {
              return {
                unioninfo: { unName: "", unLogo: "" },
                top: null,
                background: "#FFFFFF",
                title: "编辑品牌信息",
                showtip: !1,
                inputStyle: {
                  background: "#F5F5F5",
                  width: "540rpx",
                  minHeight: "45px",
                  paddingLeft: "35rpx",
                  margin: "0rpx 0rpx",
                  borderRadius: "25px",
                  color: "#7E7E7E",
                  fontWeight: "400",
                },
              };
            },
            components: {
              navigation: function () {
                o.e("components/navigation/index")
                  .then(
                    function () {
                      return resolve(o("af9e"));
                    }.bind(null, o),
                  )
                  .catch(o.oe);
              },
            },
            created: function () {
              var t = this;
              n.$on("uAvatarCropper", function (o) {
                (t.showtip = !0),
                  (t.avatar = o),
                  n.uploadFile({
                    url: "".concat(u.default.baseUrl, "/common/uploadfile"),
                    filePath: o,
                    name: "file",
                    complete: function (n) {
                      var o = JSON.parse(n.data).dbUrl,
                        e = JSON.parse(n.data).webUrl;
                      (t.unioninfo.fullUnLogo = e),
                        (t.unioninfo.unLogo = o),
                        t.$forceUpdate();
                    },
                  });
              });
            },
            computed: {
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var t = n.getMenuButtonBoundingClientRect();
                return (
                  t.height +
                  2 * (t.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            methods: {
              saveLinkInfo: function () {
                var t = {};
                (t.unlogo = this.unioninfo.unLogo),
                  (t.unname = this.unioninfo.unName),
                  this.unioninfo.unLogo
                    ? this.unioninfo.unName
                      ? (0, i.saveLinkInfo)(t).then(function (t) {
                          n.showToast({
                            title: "保存成功",
                            icon: "none",
                            mask: !0,
                            success: function () {
                              setTimeout(function () {
                                n.navigateBack();
                              }, 1e3);
                            },
                          });
                        })
                      : n.showToast({
                          title: "请填写品牌名称！",
                          icon: "none",
                          mask: !0,
                        })
                    : n.showToast({
                        title: "请上传品牌LOGO！",
                        icon: "none",
                        mask: !0,
                      });
              },
              chooseAvatar: function () {
                this.$u.route({
                  url: "/uview-ui/components/u-avatar-cropper/u-avatar-cropper",
                  params: { destWidth: 220, rectWidth: 350, fileType: "jpg" },
                });
              },
            },
            onLoad: function (n) {
              n &&
                n.unLogo &&
                "null" != n.unLogo &&
                ((this.unioninfo.unLogo = n.unLogo),
                (this.unioninfo.unName = n.unName),
                (this.unioninfo.fullUnLogo = n.fullUnLogo));
            },
            onShow: function () {
              this.showtip = !1;
            },
            onUnload: function () {
              n.$off("uAvatarCropper");
            },
          };
        t.default = a;
      }).call(this, o("df3c").default);
    },
    ea6b: function (n, t, o) {},
    f471: function (n, t, o) {
      "use strict";
      o.r(t);
      var e = o("2804"),
        i = o("035e");
      for (var u in i)
        ["default"].indexOf(u) < 0 &&
          (function (n) {
            o.d(t, n, function () {
              return i[n];
            });
          })(u);
      o("d54c");
      var a = o("828b"),
        r = Object(a.a)(
          i.default,
          e.b,
          e.c,
          !1,
          null,
          "eb2a6a4a",
          null,
          !1,
          e.a,
          void 0,
        );
      t.default = r.exports;
    },
  },
  [["c1ff", "common/runtime", "common/vendor"]],
]);
