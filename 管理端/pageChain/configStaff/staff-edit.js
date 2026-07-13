(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageChain/configStaff/staff-edit"],
  {
    bcc5: function (t, e, o) {},
    c66a: function (t, e, o) {
      "use strict";
      o.d(e, "b", function () {
        return a;
      }),
        o.d(e, "c", function () {
          return s;
        }),
        o.d(e, "a", function () {
          return n;
        });
      var n = {
          uSubsection: function () {
            return o
              .e("uview-ui/components/u-subsection/u-subsection")
              .then(o.bind(null, "52b2"));
          },
          uForm: function () {
            return o
              .e("uview-ui/components/u-form/u-form")
              .then(o.bind(null, "a809"));
          },
          uFormItem: function () {
            return Promise.all([
              o.e("common/vendor"),
              o.e("uview-ui/components/u-form-item/u-form-item"),
            ]).then(o.bind(null, "ec61"));
          },
          uInput: function () {
            return Promise.all([
              o.e("common/vendor"),
              o.e("uview-ui/components/u-input/u-input"),
            ]).then(o.bind(null, "b5ea"));
          },
          uCellItem: function () {
            return o
              .e("uview-ui/components/u-cell-item/u-cell-item")
              .then(o.bind(null, "7e47"));
          },
          uActionSheet: function () {
            return o
              .e("uview-ui/components/u-action-sheet/u-action-sheet")
              .then(o.bind(null, "6297"));
          },
          confirmModal: function () {
            return o
              .e("components/confirm-modal/confirm-modal")
              .then(o.bind(null, "4e5b"));
          },
          uModal: function () {
            return o
              .e("uview-ui/components/u-modal/u-modal")
              .then(o.bind(null, "6682"));
          },
          ffBottomLogo: function () {
            return o
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(o.bind(null, "3111"));
          },
        },
        a = function () {
          var t = this,
            e =
              (t.$createElement,
              t._self._c,
              t.imgurl ? null : t.imgsrc("/static/imgs/headimg.png")),
            o =
              "添加员工/教练" != t.title
                ? t.imgsrc("/static/imgs/camera.png")
                : null;
          t._isMounted ||
            (t.e0 = function (e) {
              t.show = !0;
            }),
            (t.$mp.data = Object.assign({}, { $root: { m0: e, m1: o } }));
        },
        s = [];
    },
    d42a: function (t, e, o) {
      "use strict";
      (function (t, e) {
        var n = o("47a9");
        o("86d2"), n(o("3240"));
        var a = n(o("f9be"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = o), e(a.default);
      }).call(this, o("3223").default, o("df3c").createPage);
    },
    d768: function (t, e, o) {
      "use strict";
      o.r(e);
      var n = o("f607"),
        a = o.n(n);
      for (var s in n)
        ["default"].indexOf(s) < 0 &&
          (function (t) {
            o.d(e, t, function () {
              return n[t];
            });
          })(s);
      e.default = a.a;
    },
    e928: function (t, e, o) {
      "use strict";
      var n = o("bcc5");
      o.n(n).a;
    },
    f607: function (t, e, o) {
      "use strict";
      (function (t, n) {
        var a = o("47a9");
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0);
        var s = o("1ba0"),
          i = a(o("7502")),
          f = {
            data: function () {
              var t = this;
              return {
                top: null,
                background: "#FFFFFF",
                title: "编辑员工",
                titleStyle: { width: "100rpx" },
                delShow: !1,
                show: !1,
                tips: { text: "请选择性别", color: "#909399", fontSize: 24 },
                modalMode: {
                  modalTitle: [
                    "警示！确认删除该员工吗？",
                    "确认该员工离职吗？",
                  ],
                  modalText: [
                    "删除后，该员工所有数据将清除，建议改为离职状态!",
                    "离职后，该员工将被清退出总店！",
                    "是否删除该员工仍在课表中的“团课/私教”课程 若已有会员预约，则将强制自动取消预约",
                  ],
                  modalStatus: 0,
                  modalCourse: 0,
                },
                modal: {
                  modalTitle: "确认删除该员工吗？",
                  modalText: "点击确定后将删除",
                  checked: !1,
                },
                list: [{ text: "男" }, { text: "女" }],
                subsectionIndex: 0,
                subsectionList: [{ name: "在职" }, { name: "离职" }],
                model: { staffSex: 2, staffSexName: "女", siteOwner: !1 },
                savedis: !1,
                imgurl: "",
                rules: {
                  staffName: [
                    { required: !0, message: "请输入姓名" },
                    { max: 20, message: "姓名不能超过20个字" },
                  ],
                  staffSexName: [{ required: !0, message: "请选择性别" }],
                  staffTel: [
                    { required: !0, message: "请输入手机号" },
                    {
                      validator: function (e, o, n) {
                        return t.$u.test.mobile(o);
                      },
                      message: "手机号码不正确",
                    },
                  ],
                },
              };
            },
            created: function () {
              var e = this;
              t.$on("uAvatarCropper", function (o) {
                (e.avatar = o),
                  t.uploadFile({
                    url: "".concat(i.default.baseUrl, "/common/uploadfile"),
                    filePath: o,
                    name: "file",
                    complete: function (t) {
                      var o = JSON.parse(t.data).dbUrl,
                        n = JSON.parse(t.data).webUrl;
                      (e.model.staffFace = o), (e.imgurl = n);
                    },
                  });
              });
            },
            methods: {
              chooseAvatar: function () {
                this.$u.route({
                  url: "/uview-ui/components/u-avatar-cropper/u-avatar-cropper",
                  params: { destWidth: 220, rectWidth: 350, fileType: "jpg" },
                });
              },
              savedata: function () {
                var t = this;
                this.$refs.uForm.validate(function (e) {
                  if (!e) return !1;
                  t.savestaff();
                });
              },
              savestaff: function () {
                var e = this;
                n.showLoading({ title: "正在保存", mask: !0 });
                var o = {};
                this.model.staffFace &&
                  -1 == this.model.staffFace.indexOf("http") &&
                  (o.staffFace = this.model.staffFace),
                  o.staffFace ||
                    this.model.staffFace ||
                    (o.staffFace = this.dict.defaultStaffFace),
                  (o.staffUserid = this.model.staffUserid),
                  (o.staffName = this.model.staffName),
                  (o.staffSex = this.model.staffSex),
                  (o.staffTel = this.model.staffTel),
                  (o.staffStatus = this.model.staffStatus),
                  (0, s.savestaff)(o).then(function (o) {
                    t.hideLoading(),
                      200 == o.code
                        ? ((e.savedis = !0),
                          t.showToast({
                            title: "保存成功",
                            icon: "none",
                            mask: !0,
                            success: function () {
                              setTimeout(function () {
                                t.navigateBack();
                              }, 1e3);
                            },
                          }))
                        : t.showToast({ title: o.msg, icon: "none", mask: !0 });
                  });
              },
              leaveWorkstaff: function () {
                var e = this;
                n.showLoading({ title: "正在保存", mask: !0 });
                var o = {};
                (o.staffUserid = this.model.staffUserid),
                  (o.staffStatus = this.model.staffStatus),
                  (0, s.changeStatus)(o).then(function (o) {
                    t.hideLoading(),
                      200 == o.code &&
                        ((e.savedis = !0),
                        t.showToast({
                          title: "保存成功",
                          icon: "none",
                          mask: !0,
                          success: function () {
                            setTimeout(function () {
                              t.navigateBack();
                            }, 1e3);
                          },
                        }));
                  });
              },
              actionSheetCallback: function (e) {
                t.hideKeyboard(),
                  (this.model.staffSexName = this.list[e].text),
                  "男" == this.list[e].text
                    ? (this.model.staffSex = 1)
                    : (this.model.staffSex = 2);
              },
              sectionChange: function (t) {
                0 == t
                  ? ((this.subsectionIndex = 0),
                    (this.model.staffStatus = 1),
                    this.leaveWorkstaff())
                  : ((this.subsectionIndex = 1),
                    (this.modal.modalText = this.modalMode.modalText[1]),
                    (this.modalMode.modalCourse = 0),
                    (this.modal.modalTitle = this.modalMode.modalTitle[1]),
                    (this.modalMode.modalStatus = 1),
                    (this.$refs.confirmModal.show = !0));
              },
              delmodal: function () {
                (this.modal.modalText = this.modalMode.modalText[0]),
                  (this.modalMode.modalCourse = 0),
                  (this.modal.modalTitle = this.modalMode.modalTitle[0]),
                  (this.modalMode.modalStatus = 0),
                  (this.$refs.confirmModal.show = !0);
              },
              cancelbtn: function () {
                (this.subsectionIndex = 0), (this.$refs.confirmModal.show = !1);
              },
              confirmbtn: function () {
                0 == this.modalMode.modalStatus
                  ? ((this.model.deldata = 1), this.delstaff())
                  : ((this.model.staffStatus = 0), this.leaveWorkstaff()),
                  (this.delShow = !1);
              },
              delstaff: function () {
                n.showLoading({ title: "正在保存", mask: !0 });
                var e = {};
                (e.staffUserid = this.model.staffUserid),
                  (e.staffStatus = 2),
                  (0, s.changeStatus)(e).then(function (e) {
                    t.hideLoading(),
                      200 == e.code &&
                        t.showToast({
                          title: "删除成功",
                          icon: "none",
                          mask: !0,
                          success: function () {
                            setTimeout(function () {
                              t.navigateBack();
                            }, 1e3);
                          },
                        });
                  });
              },
            },
            onLoad: function (t) {
              (this.title = t.staffone ? "编辑员工" : "添加员工"),
                t.staffone
                  ? ((this.model = JSON.parse(decodeURIComponent(t.staffone))),
                    console.log(this.model),
                    1 == this.model.staffStatus
                      ? (this.subsectionIndex = 0)
                      : ((this.subsectionList[1].name = "已离职"),
                        (this.subsectionIndex = 1)),
                    this.model.staffFace &&
                      (this.imgurl = this.model.staffFace),
                    0 == this.model.staffSex &&
                      ((this.model.staffSex = 2),
                      (this.model.staffSexName = "女")))
                  : ((this.model.staffSex = 2),
                    (this.model.staffSexName = "女"),
                    this.$forceUpdate());
            },
            onReady: function () {
              this.$refs.uForm.setRules(this.rules);
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
              confirmModal: function () {
                o.e("components/confirm-modal/confirm-modal")
                  .then(
                    function () {
                      return resolve(o("4e5b"));
                    }.bind(null, o),
                  )
                  .catch(o.oe);
              },
            },
            computed: {
              dict: function () {
                return this.$store.state.dictVal;
              },
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var e = t.getMenuButtonBoundingClientRect();
                return (
                  e.height +
                  2 * (e.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            onUnload: function () {
              t.$off("uAvatarCropper");
            },
          };
        e.default = f;
      }).call(this, o("df3c").default, o("3223").default);
    },
    f9be: function (t, e, o) {
      "use strict";
      o.r(e);
      var n = o("c66a"),
        a = o("d768");
      for (var s in a)
        ["default"].indexOf(s) < 0 &&
          (function (t) {
            o.d(e, t, function () {
              return a[t];
            });
          })(s);
      o("e928");
      var i = o("828b"),
        f = Object(i.a)(
          a.default,
          n.b,
          n.c,
          !1,
          null,
          "98ab7ede",
          null,
          !1,
          n.a,
          void 0,
        );
      e.default = f.exports;
    },
  },
  [["d42a", "common/runtime", "common/vendor"]],
]);
